<?php

namespace App\Http\Requests\Central;

use App\Models\Tenant;
use Illuminate\Validation\Rule;

/**
 * Reglas compartidas por la creación y la edición de una empresa (tenant): sus datos y su subdominio.
 */
class TenantCompanyRules
{
    /**
     * Subdominios que no se pueden asignar a una empresa.
     *
     * @var list<string>
     */
    public const RESERVED_SUBDOMAINS = ['www', 'admin', 'api', 'app', 'mail', 'central'];

    /**
     * @return array<string, array<mixed>>
     */
    public static function rules(?Tenant $ignore = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'nit' => [
                'required',
                'string',
                'max:20',
                'regex:/^[0-9]{5,15}(-[0-9])?$/',
                Rule::unique('tenants', 'nit')->ignore($ignore?->getTenantKey()),
            ],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'subdomain' => [
                'required',
                'string',
                // 56 = 63 (límite de nombres en PostgreSQL) - "tenant_": el schema se llama como el subdominio.
                'max:56',
                'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
                Rule::notIn([...self::RESERVED_SUBDOMAINS, ...self::centralSubdomains()]),
                Rule::unique('domains', 'domain')->ignore($ignore?->getTenantKey(), 'tenant_id'),
            ],
        ];
    }

    /**
     * Subdominios que harían coincidir el host del tenant con un dominio central
     * (p. ej. "sistema-transporte-tenant" con base "test").
     *
     * @return list<string>
     */
    private static function centralSubdomains(): array
    {
        $suffix = '.'.config('tenancy.tenant_base_domain');

        return collect(config('tenancy.central_domains'))
            ->filter(fn (string $domain): bool => str_ends_with($domain, $suffix))
            ->map(fn (string $domain): string => substr($domain, 0, -strlen($suffix)))
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'name' => 'nombre',
            'nit' => 'NIT',
            'email' => 'correo',
            'phone' => 'teléfono',
            'address' => 'dirección',
            'city' => 'ciudad',
            'subdomain' => 'subdominio',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'subdomain.unique' => 'Este subdominio ya está en uso.',
            'subdomain.regex' => 'El subdominio solo puede tener minúsculas, números y guiones (sin guion al inicio ni al final).',
        ];
    }
}
