<?php

namespace App\Actions\Migrations\Importers;

use App\Actions\Migrations\MigrationRunCancelled;

/**
 * Importador de un módulo (App\Enums\MigrationModule). Corre con la tenancy de la empresa destino
 * inicializada y lee la fuente por `$context->legacy`. Debe ser idempotente: identifica cada fila
 * por `legacy_source` + `legacy_id`, así repetir la corrida actualiza y no duplica.
 */
interface LegacyImporter
{
    /**
     * @throws MigrationRunCancelled
     */
    public function import(ImportContext $context): void;
}
