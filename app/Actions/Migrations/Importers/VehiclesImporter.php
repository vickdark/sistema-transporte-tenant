<?php

namespace App\Actions\Migrations\Importers;

use App\Enums\FuelType;
use App\Enums\VehicleClass;
use App\Enums\VehicleDocumentType;
use App\Models\Agreement;
use App\Models\Ally;
use App\Models\Part;
use App\Models\Supplier;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehiclePart;
use App\Models\VehiclePhoto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * `vehiculos` legacy con sus documentos, fotos, partes y convenios → vehículos de la empresa.
 *
 * - Fallar cerrado: un dueño (proveedor o aliado) que no se importó, un estado, clase o
 *   combustible desconocidos, o un dato obligatorio vacío mandan el vehículo a cuarentena (si no,
 *   quedaría como vehículo de la empresa o con un valor inventado). Sus documentos, fotos y partes
 *   también van a cuarentena.
 * - Un vehículo de un tercero mixto queda a nombre de los dos, como hacía el trigger del legacy.
 * - Documentos: los ocho registros vacíos que el legacy creaba por vehículo se omiten. Las fotos
 *   y los archivos se copian por el acceso SFTP de la fuente; si falta un archivo el documento entra
 *   sin él (la foto no entra) y se reporta: al copiar lo que falta y repetir la corrida se completa.
 * - Partes: el catálogo sale de los nombres usados (en el legacy era un ENUM).
 * - Convenios: un vehículo queda como mucho en uno (el de id más antiguo, que es el que salía en el
 *   FUEC); lo demás se reporta.
 * - Lo que rompe las reglas de la pantalla se migra como está y se reporta en un resumen.
 */
class VehiclesImporter implements LegacyImporter
{
    public const LEGACY_TABLE = 'vehiculos';

    public const DOCUMENTS_TABLE = 'vehiculo_documentos';

    public const PHOTOS_TABLE = 'vehiculo_fotos';

    public const PARTS_TABLE = 'partes_vehiculo';

    public const AGREEMENTS_TABLE = 'convenios';

    /**
     * Registros que no cumplen una regla de la pantalla o que se ajustaron: tabla => motivo => ids legacy.
     *
     * @var array<string, array<string, list<int|string>>>
     */
    private array $issues = [];

    /**
     * Vehículos importados: id legacy => vehículo.
     *
     * @var array<int|string, Vehicle>
     */
    private array $vehicles = [];

    public function import(ImportContext $context): void
    {
        $legacy = $context->legacy;
        $legacyVehicles = $legacy->table('vehiculos')
            ->select(
                'ID as id', 'Placa as plate', 'NumeroInterno as internal_number', 'KilometrajeActual as current_mileage',
                'FechaMatricula as registration_date', 'LugarMatricula as registration_place', 'Modelo as model_year',
                'Marca as brand', 'ClaseVehiculo as line', 'TipoVehiculo as vehicle_class', 'NumeroMotor as engine_number',
                'NumeroChasis as chassis_number', 'Color as color', 'cilindraje as displacement', 'combustible as fuel_type',
                'Estado as status', 'proveedor_id as supplier_id', 'aliadoid as ally_id',
            )
            ->orderBy('ID')
            ->get();
        $legacyAgreements = $legacy->table('convenios')->select('id', 'Nit as nit', 'RazonSocial as business_name')->orderBy('id')->get();
        $agreementVehicles = $legacy->table('convenio_vehiculos')->select('Convenio_ID as agreement_id', 'Vehiculo_ID as vehicle_id')->orderBy('Convenio_ID')->get();
        $documents = $legacy->table('vehiculo_documentos')
            ->select('id', 'id_vehiculo as vehicle_id', 'tipo_documento as type', 'numero_documento as number', 'fecha_expedicion as issued_on', 'fecha_vencimiento as expires_on', 'archivo_documento as file', 'creado_en as created_at')
            ->orderBy('id')
            ->get();
        $photos = $legacy->table('vehiculo_fotos')->select('id', 'id_vehiculo as vehicle_id', 'ruta_foto as path', 'fecha_creacion as created_at')->orderBy('id')->get();
        $parts = $legacy->table('partes_vehiculo')->select('ID as id', 'VehiculoID as vehicle_id', 'ParteNombre as name', 'KilometrajeRequerido as interval_km')->orderBy('ID')->get();

        $context->total($legacyVehicles->count() + $legacyAgreements->count() + $documents->count() + $photos->count() + $parts->count());

        if (! $context->files()->configured() && ($photos->isNotEmpty() || $documents->contains(fn (object $document): bool => filled($document->file)))) {
            $context->warning('La fuente no tiene acceso a los archivos (SFTP): los documentos se migran sin su archivo y las fotos no se migran. Configúralo en la fuente y repite la corrida para completarlos.');
        }

        $agreements = $this->importAgreements($context, $legacyAgreements);
        $agreementOf = $this->agreementOfEachVehicle($agreementVehicles, $agreements);
        $this->importVehicles($context, $legacyVehicles, $agreementOf);
        $this->importDocuments($context, $documents);
        $this->importPhotos($context, $photos);
        $this->importParts($context, $parts);

        $this->report($context);
        $context->flush();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<int|string, int> id legacy => id del convenio
     */
    private function importAgreements(ImportContext $context, Collection $rows): array
    {
        $imported = [];

        foreach ($rows as $row) {
            $context->read();

            try {
                $imported[$row->id] = DB::transaction(function () use ($context, $row): int {
                    $nit = (string) preg_replace('/\s+/', '', (string) $row->nit);
                    $name = self::clean($row->business_name);

                    if ($nit === '' || $name === null) {
                        throw new QuarantinedRecord('El convenio no tiene NIT o razón social.', ['nit' => $row->nit]);
                    }

                    $agreement = Agreement::query()->where('legacy_source', self::AGREEMENTS_TABLE)->where('legacy_id', (string) $row->id)->first();
                    $repeated = Agreement::query()->where('nit', $nit)->when($agreement, fn ($query) => $query->whereKeyNot($agreement->id))->first();

                    if ($repeated !== null) {
                        throw new QuarantinedRecord("Su NIT {$nit} ya es de otro convenio ({$repeated->business_name}).", ['nit' => $nit]);
                    }

                    $isNew = $agreement === null;
                    $agreement ??= new Agreement;
                    $agreement->forceFill(['nit' => $nit, 'business_name' => $name, 'legacy_source' => self::AGREEMENTS_TABLE, 'legacy_id' => (string) $row->id])->save();
                    $isNew ? $context->inserted() : $context->updated();

                    return $agreement->id;
                });
            } catch (QuarantinedRecord $record) {
                $context->quarantine(self::AGREEMENTS_TABLE, $row->id, $record);
            }

            $context->tick();
        }

        return $imported;
    }

    /**
     * Convenio de cada vehículo: el de id más antiguo si estaba en varios.
     *
     * @param  Collection<int, object>  $rows
     * @param  array<int|string, int>  $agreements
     * @return array<int|string, int> id legacy del vehículo => id del convenio
     */
    private function agreementOfEachVehicle(Collection $rows, array $agreements): array
    {
        $agreementOf = [];

        foreach ($rows as $row) {
            if (! isset($agreements[$row->agreement_id])) {
                continue;
            }

            if (isset($agreementOf[$row->vehicle_id])) {
                $this->issues[self::LEGACY_TABLE]['vehículos en más de un convenio: quedan solo en el más antiguo'][] = $row->vehicle_id;

                continue;
            }

            $agreementOf[$row->vehicle_id] = $agreements[$row->agreement_id];
        }

        return $agreementOf;
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array<int|string, int>  $agreementOf
     */
    private function importVehicles(ImportContext $context, Collection $rows, array $agreementOf): void
    {
        $suppliers = Supplier::query()->where('legacy_source', ThirdPartiesImporter::SUPPLIERS_TABLE)->get()->keyBy('legacy_id');
        $allies = Ally::query()->with('supplier')->where('legacy_source', ThirdPartiesImporter::ALLIES_TABLE)->get()->keyBy('legacy_id');

        foreach ($rows as $row) {
            $context->read();

            try {
                $this->vehicles[$row->id] = DB::transaction(fn (): Vehicle => $this->importVehicle($context, $row, $suppliers, $allies, $agreementOf[$row->id] ?? null));
            } catch (QuarantinedRecord $record) {
                $context->quarantine(self::LEGACY_TABLE, $row->id, $record);
            }

            $context->tick();
        }
    }

    /**
     * @param  Collection<string, Supplier>  $suppliers
     * @param  Collection<string, Ally>  $allies
     */
    private function importVehicle(ImportContext $context, object $row, Collection $suppliers, Collection $allies, ?int $agreementId): Vehicle
    {
        $plate = mb_strtoupper((string) preg_replace('/\s+/', '', (string) $row->plate));
        $payload = ['plate' => $plate];
        $data = $this->vehicleData($row, $payload);

        $vehicle = Vehicle::query()->where('legacy_source', self::LEGACY_TABLE)->where('legacy_id', (string) $row->id)->first();
        $repeated = Vehicle::query()->where('plate', $plate)->when($vehicle, fn ($query) => $query->whereKeyNot($vehicle->id))->first();

        if ($repeated !== null) {
            throw new QuarantinedRecord("La placa {$plate} ya es de otro vehículo (vehiculos #{$repeated->legacy_id}).", $payload);
        }

        if (preg_match('/^[A-Z]{3}[0-9]{3}$/', $plate) !== 1) {
            $this->issues[self::LEGACY_TABLE]['placa con un formato distinto de 3 letras y 3 números'][] = $row->id;
        }

        $isNew = $vehicle === null;
        $vehicle ??= new Vehicle;
        $vehicle->forceFill([
            ...$data,
            'plate' => $plate,
            'agreement_id' => $agreementId,
            'legacy_source' => self::LEGACY_TABLE,
            'legacy_id' => (string) $row->id,
        ]);
        $this->assignOwner($vehicle, $row, $suppliers, $allies, $payload);
        $vehicle->save();

        $isNew ? $context->inserted() : $context->updated();

        return $vehicle;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function vehicleData(object $row, array $payload): array
    {
        $required = [
            'registration_place' => 'lugar de matrícula', 'brand' => 'marca', 'line' => 'línea',
            'engine_number' => 'número de motor', 'chassis_number' => 'número de chasis', 'color' => 'color',
        ];
        $missing = array_values(array_filter($required, fn (string $label, string $column): bool => self::clean($row->{$column}) === null, ARRAY_FILTER_USE_BOTH));
        $registrationDate = self::date($row->registration_date);

        if ($registrationDate === null) {
            $missing[] = 'fecha de matrícula';
        }

        if ($missing !== []) {
            throw new QuarantinedRecord('Le faltan datos obligatorios: '.implode(', ', $missing).'.', $payload);
        }

        $isActive = match (mb_strtolower(trim((string) $row->status))) {
            'activo' => true,
            'inactivo' => false,
            default => throw new QuarantinedRecord("Estado desconocido: \"{$row->status}\".", $payload),
        };
        $class = VehicleClass::fromLegacyText($row->vehicle_class)
            ?? throw new QuarantinedRecord("Clase de vehículo desconocida: \"{$row->vehicle_class}\".", $payload);
        $fuel = self::clean($row->fuel_type) === null ? null : (FuelType::fromLegacyText((string) $row->fuel_type)
            ?? throw new QuarantinedRecord("Combustible desconocido: \"{$row->fuel_type}\".", $payload));
        $modelYear = trim((string) $row->model_year);

        if (preg_match('/^\d{4}$/', $modelYear) !== 1) {
            throw new QuarantinedRecord("El modelo \"{$row->model_year}\" no es un año.", $payload);
        }

        $displacement = self::clean($row->displacement);

        if ($displacement !== null && ! ctype_digit($displacement)) {
            $this->issues[self::LEGACY_TABLE]['cilindraje que no es un número: se migra vacío'][] = $row->id;
            $displacement = null;
        }

        return [
            'internal_number' => self::clean($row->internal_number),
            'current_mileage' => (float) ($row->current_mileage ?? 0),
            'registration_date' => $registrationDate,
            'registration_place' => self::clean($row->registration_place),
            'model_year' => (int) $modelYear,
            'brand' => self::clean($row->brand),
            'line' => self::clean($row->line),
            'vehicle_class' => $class,
            'engine_number' => self::clean($row->engine_number),
            'chassis_number' => self::clean($row->chassis_number),
            'color' => self::clean($row->color),
            'displacement' => $displacement !== null ? (int) $displacement : null,
            'fuel_type' => $fuel,
            'is_active' => $isActive,
        ];
    }

    /**
     * El dueño se resuelve con los terceros ya importados. Si es un tercero mixto, el vehículo
     * queda a nombre de los dos (lo que hacía el trigger del legacy al crearlo).
     *
     * @param  Collection<string, Supplier>  $suppliers
     * @param  Collection<string, Ally>  $allies
     * @param  array<string, mixed>  $payload
     */
    private function assignOwner(Vehicle $vehicle, object $row, Collection $suppliers, Collection $allies, array $payload): void
    {
        $supplierId = (int) $row->supplier_id ?: null;
        $allyId = (int) $row->ally_id ?: null;
        $supplier = $supplierId !== null ? ($suppliers->get((string) $supplierId) ?? throw new QuarantinedRecord("Su proveedor #{$supplierId} no está importado. Migra primero los aliados y proveedores.", $payload)) : null;
        $ally = $allyId !== null ? ($allies->get((string) $allyId) ?? throw new QuarantinedRecord("Su aliado #{$allyId} no está importado. Migra primero los aliados y proveedores.", $payload)) : null;

        if ($supplier !== null && $ally !== null) {
            $vehicle->supplier_id = $supplier->id;
            $vehicle->ally_id = $ally->id;

            if ($supplier->ally_id !== $ally->id) {
                $this->issues[self::LEGACY_TABLE]['a nombre de un proveedor y un aliado que no son el mismo tercero mixto'][] = $row->id;
            }

            return;
        }

        $vehicle->assignOwner($supplier ?? $ally);

        if ($vehicle->supplier_id !== null && $vehicle->ally_id !== null) {
            $this->issues[self::LEGACY_TABLE]['de un tercero mixto pero a nombre de uno solo: quedan a nombre de los dos'][] = $row->id;
        }
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    private function importDocuments(ImportContext $context, Collection $rows): void
    {
        foreach ($rows as $row) {
            $context->read();

            if (self::clean($row->number) === null && self::date($row->issued_on) === null && self::date($row->expires_on) === null && self::clean($row->file) === null) {
                $context->skipped();
                $context->tick();

                continue;
            }

            try {
                DB::transaction(fn () => $this->importDocument($context, $row));
            } catch (QuarantinedRecord $record) {
                $context->quarantine(self::DOCUMENTS_TABLE, $row->id, $record);
            }

            $context->tick();
        }
    }

    private function importDocument(ImportContext $context, object $row): void
    {
        $payload = ['vehicle_id' => $row->vehicle_id, 'type' => $row->type];
        $vehicle = $this->vehicles[$row->vehicle_id] ?? throw new QuarantinedRecord("Su vehículo #{$row->vehicle_id} no está importado.", $payload);
        $type = VehicleDocumentType::fromLegacyText($row->type) ?? throw new QuarantinedRecord("Tipo de documento desconocido: \"{$row->type}\".", $payload);

        $document = VehicleDocument::query()->where('legacy_source', self::DOCUMENTS_TABLE)->where('legacy_id', (string) $row->id)->first();
        $repeated = $vehicle->currentDocuments()->where('type', $type)->when($document, fn ($query) => $query->whereKeyNot($document->id))->first();

        if ($repeated !== null) {
            throw new QuarantinedRecord("El vehículo {$vehicle->plate} ya tiene otro documento \"{$type->label()}\" (vehiculo_documentos #{$repeated->legacy_id}).", $payload);
        }

        $issuedOn = self::date($row->issued_on);
        $expiresOn = self::date($row->expires_on);

        if ($type->expires() && $expiresOn === null) {
            $this->issues[self::DOCUMENTS_TABLE]['documentos sin fecha de vencimiento (al editarlos se pedirá)'][] = $row->id;
        }

        if ($issuedOn !== null && $expiresOn !== null && $expiresOn < $issuedOn) {
            $this->issues[self::DOCUMENTS_TABLE]['documentos que vencen antes de su expedición'][] = $row->id;
        }

        $isNew = $document === null;
        $document ??= new VehicleDocument;
        $document->forceFill([
            'vehicle_id' => $vehicle->id,
            'type' => $type,
            'number' => self::clean($row->number),
            'issued_on' => $issuedOn,
            'expires_on' => $expiresOn,
            'legacy_source' => self::DOCUMENTS_TABLE,
            'legacy_id' => (string) $row->id,
        ]);

        if ($row->created_at !== null) {
            $document->created_at = Carbon::parse($row->created_at);
        }

        $document->file_path = $this->copyFile($context, $row->file, "vehicles/{$vehicle->id}/documents/legacy-{$row->id}", $document->file_path, self::DOCUMENTS_TABLE, $row->id);
        $document->save();

        $isNew ? $context->inserted() : $context->updated();
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    private function importPhotos(ImportContext $context, Collection $rows): void
    {
        foreach ($rows as $row) {
            $context->read();

            try {
                DB::transaction(fn () => $this->importPhoto($context, $row));
            } catch (QuarantinedRecord $record) {
                $context->quarantine(self::PHOTOS_TABLE, $row->id, $record);
            }

            $context->tick();
        }

        foreach ($rows->countBy('vehicle_id') as $vehicleId => $count) {
            if ($count > VehiclePhoto::MAX_PER_VEHICLE && isset($this->vehicles[$vehicleId])) {
                $this->issues[self::LEGACY_TABLE]['vehículos con más de '.VehiclePhoto::MAX_PER_VEHICLE.' fotos: se migran todas'][] = $vehicleId;
            }
        }
    }

    private function importPhoto(ImportContext $context, object $row): void
    {
        $vehicle = $this->vehicles[$row->vehicle_id]
            ?? throw new QuarantinedRecord("Su vehículo #{$row->vehicle_id} no está importado.", ['vehicle_id' => $row->vehicle_id]);

        $photo = VehiclePhoto::query()->where('legacy_source', self::PHOTOS_TABLE)->where('legacy_id', (string) $row->id)->first();
        $path = $this->copyFile($context, $this->photoPath($context, $row->path, $vehicle), "vehicles/{$vehicle->id}/photos/legacy-{$row->id}", $photo?->path, self::PHOTOS_TABLE, $row->id);

        if ($path === null) {
            $context->skipped();

            return;
        }

        $isNew = $photo === null;
        $photo ??= new VehiclePhoto;
        $photo->forceFill(['vehicle_id' => $vehicle->id, 'path' => $path, 'legacy_source' => self::PHOTOS_TABLE, 'legacy_id' => (string) $row->id]);

        if ($row->created_at !== null) {
            $photo->created_at = Carbon::parse($row->created_at);
        }

        $photo->save();

        $isNew ? $context->inserted() : $context->updated();
    }

    /**
     * El legacy guarda las fotos en una carpeta con la placa; si la placa cambió, renombraba la
     * carpeta pero no las rutas guardadas. Si la ruta guardada no existe se busca en la de la placa actual.
     */
    private function photoPath(ImportContext $context, ?string $path, Vehicle $vehicle): ?string
    {
        if ($path === null || $context->files()->locate($path) !== null) {
            return $path;
        }

        $current = 'uploads/vehiculos/'.$vehicle->plate.'/'.basename(str_replace('\\', '/', $path));

        return $context->files()->locate($current) !== null ? $current : $path;
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    private function importParts(ImportContext $context, Collection $rows): void
    {
        // El catálogo se arma antes, fuera de la transacción de cada registro.
        $catalog = $rows
            ->map(fn (object $row): string => Part::clean((string) $row->name))
            ->filter()
            ->unique(fn (string $name): string => mb_strtolower($name))
            ->mapWithKeys(fn (string $name): array => [mb_strtolower($name) => (Part::findByName($name) ?? Part::create(['name' => $name]))->id])
            ->all();

        foreach ($rows as $row) {
            $context->read();

            try {
                DB::transaction(function () use ($context, $row, $catalog): void {
                    $vehicle = $this->vehicles[$row->vehicle_id]
                        ?? throw new QuarantinedRecord("Su vehículo #{$row->vehicle_id} no está importado.", ['vehicle_id' => $row->vehicle_id, 'name' => $row->name]);
                    $name = Part::clean((string) $row->name);

                    if ($name === '') {
                        throw new QuarantinedRecord('La parte no tiene nombre.', ['vehicle_id' => $row->vehicle_id]);
                    }

                    $partId = $catalog[mb_strtolower($name)];
                    $intervalKm = (int) round((float) $row->interval_km);

                    if ($intervalKm <= 0) {
                        $this->issues[self::PARTS_TABLE]['partes con kilometraje entre mantenimientos de 0 o menos (al editarlas se pedirá corregirlo)'][] = $row->id;
                    }

                    $vehiclePart = VehiclePart::query()->where('legacy_source', self::PARTS_TABLE)->where('legacy_id', (string) $row->id)->first()
                        ?? VehiclePart::query()->where('vehicle_id', $vehicle->id)->where('part_id', $partId)->first();

                    if ($vehiclePart !== null && $vehiclePart->legacy_id !== null && $vehiclePart->legacy_id !== (string) $row->id) {
                        throw new QuarantinedRecord("El vehículo {$vehicle->plate} ya tiene la parte \"{$name}\" (partes_vehiculo #{$vehiclePart->legacy_id}).", ['vehicle_id' => $row->vehicle_id, 'name' => $name]);
                    }

                    $isNew = $vehiclePart === null;
                    $vehiclePart ??= new VehiclePart;
                    $vehiclePart->forceFill([
                        'vehicle_id' => $vehicle->id,
                        'part_id' => $partId,
                        'maintenance_interval_km' => max($intervalKm, 0),
                        'legacy_source' => self::PARTS_TABLE,
                        'legacy_id' => (string) $row->id,
                    ])->save();

                    $isNew ? $context->inserted() : $context->updated();
                });
            } catch (QuarantinedRecord $record) {
                $context->quarantine(self::PARTS_TABLE, $row->id, $record);
            }

            $context->tick();
        }
    }

    /**
     * Copia un archivo legacy al disco de la empresa y devuelve su ruta. Si no se encuentra, conserva
     * el que ya se había copiado en una corrida anterior (o null) y lo reporta.
     */
    private function copyFile(ImportContext $context, ?string $legacyPath, string $destinationWithoutExtension, ?string $previous, string $table, int|string $legacyId): ?string
    {
        if (self::clean($legacyPath) === null) {
            return null;
        }

        $files = $context->files();

        if (! $files->configured()) {
            return $previous;
        }

        $found = $files->locate($legacyPath);

        if ($found === null) {
            $this->issues[$table]['archivos que no están en la carpeta del legacy'][] = $legacyId;

            return $previous;
        }

        $destination = $destinationWithoutExtension.'.'.mb_strtolower(pathinfo($found, PATHINFO_EXTENSION));

        if (! $files->copy($found, Vehicle::FILES_DISK, $destination)) {
            $this->issues[$table]['archivos que no se copiaron completos (checksum distinto)'][] = $legacyId;

            return $previous;
        }

        return $destination;
    }

    private function report(ImportContext $context): void
    {
        foreach ($this->issues as $table => $reasons) {
            foreach ($reasons as $reason => $legacyIds) {
                $legacyIds = array_values(array_unique($legacyIds));

                $count = count($legacyIds);
                $context->warning("{$count} ".($count === 1 ? 'registro' : 'registros')." de {$table}: {$reason}.", $table, payload: ['legacy_ids' => $legacyIds]);
            }
        }
    }

    private static function date(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' || str_starts_with($value, '0000-00-00') ? null : Carbon::parse($value)->toDateString();
    }

    private static function clean(mixed $value): ?string
    {
        $value = preg_replace('/\s+/', ' ', trim((string) $value));

        return $value === '' ? null : $value;
    }
}
