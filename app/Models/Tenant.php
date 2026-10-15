<?php

namespace App\Models;

use App\Enums\TenantStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * Empresa de transporte. Vive en el schema central; sus datos de negocio (usuarios, órdenes…)
 * viven en su propio schema de PostgreSQL, que se crea y migra al crear la empresa.
 *
 * @property string $id
 * @property string $name
 * @property string|null $nit
 * @property TenantStatus $status
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $city
 * @property Carbon|null $suspended_at
 * @property int|null $initial_user_id
 * @property-read Domain|null $subdomain
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    /** @use HasFactory<TenantFactory> */
    use HasDatabase, HasDomains, HasFactory;

    /**
     * Columnas reales de la tabla; cualquier otro atributo se guarda en el JSON `data`.
     *
     * @return list<string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'nit',
            'status',
            'email',
            'phone',
            'address',
            'city',
            'suspended_at',
            'initial_user_id',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * Registro de `domains` con el subdominio del tenant.
     *
     * @return HasOne<Domain, $this>
     */
    public function subdomain(): HasOne
    {
        return $this->hasOne(Domain::class);
    }

    /**
     * Cantidad de usuarios de la empresa (están en su schema, no en el central).
     */
    public function usersCount(): int
    {
        return $this->run(fn (): int => User::count());
    }

    /**
     * Administrador creado en el alta (vive en el schema de la empresa). Para empresas
     * anteriores a `initial_user_id` se toma el primer usuario.
     */
    public function initialUser(): ?User
    {
        return $this->run(fn (): ?User => $this->initial_user_id
            ? User::find($this->initial_user_id)
            : User::query()->orderBy('id')->first());
    }

    public function isSuspended(): bool
    {
        return $this->status === TenantStatus::Suspended;
    }

    /**
     * Nombre legible del schema de una empresa: tenant_ + subdominio (kemuel-sas → tenant_kemuel_sas).
     * Los subdominios no admiten "_", así que dos subdominios nunca dan el mismo schema.
     */
    public static function schemaNameFor(string $subdomain): string
    {
        return config('tenancy.database.prefix').str_replace('-', '_', $subdomain);
    }

    /**
     * Renombra el schema para que siga al subdominio. Es instantáneo en PostgreSQL y no mueve datos.
     * Debe correr sin la tenancy de esta empresa inicializada.
     */
    public function renameSchemaFor(string $subdomain): void
    {
        $current = $this->database()->getName();
        $target = static::schemaNameFor($subdomain);

        if ($current === $target) {
            return;
        }

        DB::connection(config('tenancy.database.central_connection'))
            ->statement("ALTER SCHEMA \"{$current}\" RENAME TO \"{$target}\"");

        $this->setInternal('db_name', $target);
        $this->save();
    }

    /**
     * Host completo del tenant: su subdominio sobre el dominio base de los tenants (p. ej. demo.test).
     */
    public function host(): string
    {
        return $this->subdomain->domain.'.'.config('tenancy.tenant_base_domain');
    }

    /**
     * URL del tenant con el esquema de APP_URL.
     */
    public function url(string $path = '/'): string
    {
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'https';

        return "{$scheme}://{$this->host()}/".ltrim($path, '/');
    }
}
