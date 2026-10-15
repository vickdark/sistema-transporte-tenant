<?php

namespace App\Enums;

/**
 * Clase de contrato: propio (cliente de la empresa) o de aliado (cliente de un aliado). Son
 * módulos distintos, cada uno con su numeración para el FUEC.
 */
enum ContractKind: string
{
    case Own = 'own';
    case Ally = 'ally';

    public function label(): string
    {
        return match ($this) {
            self::Own => 'Contratos',
            self::Ally => 'Contratos de aliados',
        };
    }
}
