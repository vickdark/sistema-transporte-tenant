<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Concerns\HasScopedValidationRules;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * Empresa de transporte. Single-database tenancy: todos los tenants comparten la BD central
 * y sus registros se separan por la columna tenant_id.
 *
 * @property string $id
 * @property string|null $name
 */
class Tenant extends BaseTenant
{
    /** @use HasFactory<TenantFactory> */
    use HasDomains, HasFactory, HasScopedValidationRules;
}
