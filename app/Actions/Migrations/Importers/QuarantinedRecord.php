<?php

namespace App\Actions\Migrations\Importers;

use RuntimeException;

/**
 * Un registro legacy que no se puede importar sin inventar datos: va a cuarentena y la corrida
 * sigue con el siguiente. Lanzarla dentro de la transacción del registro deshace lo que alcanzó
 * a escribir.
 */
class QuarantinedRecord extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $payload  datos para corregir el registro (nunca contraseñas)
     */
    public function __construct(string $message, public readonly array $payload = [])
    {
        parent::__construct($message);
    }
}
