<?php

namespace App\Actions\Migrations;

use RuntimeException;

/**
 * El administrador canceló la corrida; el paso en curso se interrumpe en el siguiente lote.
 */
class MigrationRunCancelled extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La corrida fue cancelada.');
    }
}
