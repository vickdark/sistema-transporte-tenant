<?php

namespace App\Actions\Migrations\Importers;

use App\Models\CompanyProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * `configuracion_empresa` legacy → configuración de la empresa (contacto, tipo de servicio y
 * datos de habilitación del FUEC). La razón social y el NIT no se importan: son del inquilino
 * (el NIT ya se verificó al empezar la corrida); si la razón social difiere solo se avisa.
 * El logo y la firma del legacy son archivos del código, no de la base: se suben a mano.
 */
class CompanyImporter implements LegacyImporter
{
    public const LEGACY_TABLE = 'configuracion_empresa';

    public function import(ImportContext $context): void
    {
        $legacyCompany = $context->legacy->table('configuracion_empresa')
            ->select('id', 'razon_social', 'direccion', 'telefono', 'correo', 'tipo_servicio', 'codigo_territorial', 'resolucion_habilitacion', 'anio_habilitacion')
            ->orderBy('id')
            ->first();

        $context->total($legacyCompany ? 1 : 0);

        if ($legacyCompany === null) {
            $context->flush();

            return;
        }

        $context->read();

        try {
            DB::transaction(fn () => $this->importCompany($context, $legacyCompany));
        } catch (QuarantinedRecord $record) {
            $context->quarantine(self::LEGACY_TABLE, $legacyCompany->id, $record);
        }

        $context->warning('El logo y la firma del legacy son archivos del sistema anterior, no datos: súbelos en Configuración de la empresa.');

        $context->flush();
    }

    private function importCompany(ImportContext $context, object $legacyCompany): void
    {
        $data = [
            'address' => trim((string) $legacyCompany->direccion),
            'phone' => trim((string) $legacyCompany->telefono),
            'email' => Str::lower(trim((string) $legacyCompany->correo)),
            'service_type' => trim((string) $legacyCompany->tipo_servicio) ?: CompanyProfile::DEFAULT_SERVICE_TYPE,
            'territorial_code' => trim((string) $legacyCompany->codigo_territorial),
            'habilitation_resolution' => str_pad(trim((string) $legacyCompany->resolucion_habilitacion), 4, '0', STR_PAD_LEFT),
            'habilitation_year' => trim((string) $legacyCompany->anio_habilitacion),
        ];

        $validator = Validator::make($data, [
            'email' => ['nullable', 'email'],
            'territorial_code' => ['required', 'digits:3'],
            'habilitation_resolution' => ['required', 'digits:4'],
            'habilitation_year' => ['required', 'digits:2'],
        ]);

        // Un dato de habilitación mal formado daría un número de FUEC inválido: no se adivina.
        if ($validator->fails()) {
            throw new QuarantinedRecord(
                'Datos de habilitación o correo con formato inválido: '.implode(' ', $validator->errors()->all()),
                $data,
            );
        }

        $profile = CompanyProfile::current();
        $wasImported = $profile->legacy_id !== null;

        $profile->forceFill([
            ...$data,
            'legacy_source' => self::LEGACY_TABLE,
            'legacy_id' => (string) $legacyCompany->id,
        ])->save();

        $tenantName = (string) tenant('name');

        if (Str::lower(trim((string) $legacyCompany->razon_social)) !== Str::lower(trim($tenantName))) {
            $context->warning(
                "La razón social del legacy (\"{$legacyCompany->razon_social}\") es distinta a la registrada en el panel central (\"{$tenantName}\"). "
                    .'Los documentos usan la del panel central; corrígela allí si hace falta.',
                self::LEGACY_TABLE,
                $legacyCompany->id,
            );
        }

        $wasImported ? $context->updated() : $context->inserted();
    }
}
