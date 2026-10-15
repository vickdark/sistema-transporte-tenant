<?php

namespace App\Actions\Migrations\Importers;

use App\Enums\ClientType;
use App\Enums\IdentificationType;
use App\Models\Ally;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * `clientes` legacy → clientes de la empresa o de sus aliados.
 *
 * - Fallar cerrado: tipo, tipo de identificación o estado desconocidos, o un aliado dueño que no
 *   se importó, mandan el cliente a cuarentena (si no, quedaría visible como cliente de la empresa).
 * - Los datos se conservan como están (solo sin espacios sobrantes; el teléfono sin espacios ni
 *   +57 cuando así cumple el formato). Lo que no cumple las validaciones de la pantalla se reporta
 *   en un resumen, igual que las identificaciones repetidas del mismo dueño.
 * - Enlaza los usuarios ya importados que pertenecen a un cliente (se relee `usuarios`).
 */
class ClientsImporter implements LegacyImporter
{
    public const LEGACY_TABLE = 'clientes';

    /**
     * Registros que no cumplen una validación de la pantalla: motivo => ids legacy.
     *
     * @var array<string, list<int|string>>
     */
    private array $formatIssues = [];

    public function import(ImportContext $context): void
    {
        $legacyClients = $context->legacy->table('clientes')
            ->select(
                'ID as id', 'Tipo as type', 'TipoIdentificacion as identification_type', 'Identificacion as identification',
                'DV as check_digit', 'CodigoSucursal as branch_code', 'Nombres as first_names', 'Apellidos as last_names',
                'RazonSocial as business_name', 'NombreComercial as trade_name', 'Ciudad as city', 'Direccion as address',
                'Telefono as phone', 'Extension as extension', 'estado as status', 'aliadoid as ally_id',
            )
            ->orderBy('ID')
            ->get();

        $context->total($legacyClients->count());
        $allies = Ally::query()->where('legacy_source', ThirdPartiesImporter::ALLIES_TABLE)->pluck('id', 'legacy_id')->all();
        $clients = [];

        foreach ($legacyClients as $legacyClient) {
            $context->read();

            try {
                $clients[$legacyClient->id] = DB::transaction(fn (): int => $this->importClient($context, $legacyClient, $allies));
            } catch (QuarantinedRecord $record) {
                $context->quarantine(self::LEGACY_TABLE, $legacyClient->id, $record);
            }

            $context->tick();
        }

        $this->reportFormatIssues($context);
        $this->reportRepeated($context, $legacyClients);
        $this->linkUsers($context, $clients);

        $context->flush();
    }

    /**
     * @param  array<int|string, int>  $allies
     */
    private function importClient(ImportContext $context, object $legacyClient, array $allies): int
    {
        $payload = ['identification' => $legacyClient->identification, 'ally_id' => $legacyClient->ally_id];
        $type = match (self::normalized($legacyClient->type)) {
            'persona' => ClientType::Person,
            'empresa' => ClientType::Company,
            default => throw new QuarantinedRecord("Tipo de cliente desconocido: \"{$legacyClient->type}\".", $payload),
        };
        $identificationType = match (self::normalized($legacyClient->identification_type)) {
            'cedula de ciudadania' => IdentificationType::CitizenId,
            'nit' => IdentificationType::Nit,
            default => throw new QuarantinedRecord("Tipo de identificación desconocido: \"{$legacyClient->identification_type}\".", $payload),
        };
        $isActive = match (self::normalized($legacyClient->status)) {
            'activo' => true,
            'inactivo' => false,
            default => throw new QuarantinedRecord("Estado desconocido: \"{$legacyClient->status}\".", $payload),
        };

        $allyId = null;

        if ($legacyClient->ally_id !== null) {
            $allyId = $allies[(string) $legacyClient->ally_id] ?? throw new QuarantinedRecord(
                "Su aliado #{$legacyClient->ally_id} no está importado: no se sabe a quién pertenece. Migra primero aliados y proveedores.",
                $payload,
            );
        }

        $data = [
            'type' => $type,
            'identification_type' => $identificationType,
            'identification' => self::clean($legacyClient->identification) ?? '',
            'check_digit' => self::clean($legacyClient->check_digit),
            'branch_code' => self::clean($legacyClient->branch_code),
            'first_names' => self::clean($legacyClient->first_names),
            'last_names' => self::clean($legacyClient->last_names),
            'business_name' => self::clean($legacyClient->business_name),
            'trade_name' => self::clean($legacyClient->trade_name),
            'city' => self::clean($legacyClient->city) ?? '',
            'address' => self::clean($legacyClient->address) ?? '',
            'phone' => $this->phone($legacyClient),
            'extension' => self::clean($legacyClient->extension),
            'is_active' => $isActive,
            'ally_id' => $allyId,
            'legacy_source' => self::LEGACY_TABLE,
            'legacy_id' => (string) $legacyClient->id,
        ];

        $this->collectFormatIssues($legacyClient->id, $data);

        $client = Client::query()->where('legacy_source', self::LEGACY_TABLE)->where('legacy_id', (string) $legacyClient->id)->first();
        $isNew = $client === null;

        $client ??= new Client;
        $client->forceFill($data)->save();
        $isNew ? $context->inserted() : $context->updated();

        return $client->id;
    }

    /**
     * Teléfono sin espacios, guiones ni +57 si así queda en el formato de la pantalla; si no, tal
     * cual venía (y se reporta).
     */
    private function phone(object $legacyClient): string
    {
        $raw = self::clean($legacyClient->phone) ?? '';
        $digits = preg_replace('/\D/', '', $raw);
        $digits = strlen($digits) === 12 && str_starts_with($digits, '57') ? substr($digits, 2) : $digits;

        return preg_match('/^\d{7,15}$/', $digits) === 1 ? $digits : $raw;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function collectFormatIssues(int|string $legacyId, array $data): void
    {
        $checks = [
            'identificación que no tiene entre 6 y 20 dígitos' => preg_match('/^\d{6,20}$/', $data['identification']) !== 1,
            'teléfono que no tiene entre 7 y 15 dígitos' => preg_match('/^\d{7,15}$/', $data['phone']) !== 1,
            'persona sin nombres o apellidos' => $data['type'] === ClientType::Person && ($data['first_names'] === null || $data['last_names'] === null),
            'empresa sin razón social' => $data['type'] === ClientType::Company && $data['business_name'] === null,
        ];

        foreach (array_keys(array_filter($checks)) as $reason) {
            $this->formatIssues[$reason][] = $legacyId;
        }
    }

    private function reportFormatIssues(ImportContext $context): void
    {
        foreach ($this->formatIssues as $reason => $legacyIds) {
            $context->warning(
                count($legacyIds)." clientes con {$reason}. Se migran como están; al editarlos se pedirá corregirlo.",
                self::LEGACY_TABLE,
                payload: ['legacy_ids' => $legacyIds],
            );
        }
    }

    /**
     * Misma identificación y sucursal dentro del mismo dueño: duplicados que se migran todos
     * (otros datos pueden apuntar a cualquiera) y se reportan para depurarlos.
     *
     * @param  Collection<int, object>  $legacyClients
     */
    private function reportRepeated(ImportContext $context, Collection $legacyClients): void
    {
        $groups = $legacyClients
            ->groupBy(fn (object $client): string => implode('|', [$client->ally_id ?? 'empresa', trim((string) $client->identification), trim((string) $client->branch_code)]))
            ->filter(fn (Collection $group): bool => $group->count() > 1);

        foreach ($groups as $group) {
            $first = $group->first();
            $owner = $first->ally_id === null ? 'la empresa' : "el aliado #{$first->ally_id}";

            $context->warning(
                "{$group->count()} clientes de {$owner} con la identificación {$first->identification}"
                    .(trim((string) $first->branch_code) !== '' ? " y la sucursal {$first->branch_code}" : '')
                    .' ('.$group->pluck('id')->map(fn ($id): string => "#{$id}")->implode(', ').'). Se migran todos; conviene depurarlos.',
                self::LEGACY_TABLE,
                $first->id,
                ['legacy_ids' => $group->pluck('id')->all()],
            );
        }
    }

    /**
     * @param  array<int|string, int>  $clients
     */
    private function linkUsers(ImportContext $context, array $clients): void
    {
        $legacyUsers = $context->legacy->table('usuarios')
            ->select('ID as id', 'ClienteID as client_id')
            ->whereNotNull('ClienteID')
            ->get();

        foreach ($legacyUsers as $legacyUser) {
            User::query()
                ->where('legacy_source', UsersImporter::LEGACY_TABLE)
                ->where('legacy_id', (string) $legacyUser->id)
                ->whereNull('ally_id')
                ->whereNull('supplier_id')
                ->update(['client_id' => $clients[$legacyUser->client_id] ?? null]);
        }
    }

    private static function clean(mixed $value): ?string
    {
        $value = preg_replace('/\s+/', ' ', trim((string) $value));

        return $value === '' ? null : $value;
    }

    /**
     * Minúsculas y sin tildes, para comparar los enums del legacy.
     */
    private static function normalized(mixed $value): string
    {
        return Str::lower(Str::ascii(trim((string) $value)));
    }
}
