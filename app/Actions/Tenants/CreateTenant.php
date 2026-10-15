<?php

namespace App\Actions\Tenants;

use App\Enums\TenantStatus;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Throwable;

/**
 * Alta de una empresa: crea el tenant (y con él su schema tenant_{subdominio}, ya migrado), su
 * subdominio y su primer usuario administrador. El administrador recibe una invitación para
 * definir su contraseña, o queda activo con la contraseña que se definió en el panel.
 *
 * No usa una transacción: el schema se migra por otra conexión y no vería un schema sin
 * confirmar. Si algo falla después de crear el tenant, se elimina (y con él su schema).
 */
class CreateTenant
{
    public function __construct(private SendTenantUserInvitation $sendInvitation) {}

    /**
     * @param  array{name: string, nit: string, email?: string|null, phone?: string|null, address?: string|null, city?: string|null}  $company
     * @param  array{name: string, username: string, email?: string|null, password?: string|null}  $administrator  sin password se envía invitación
     *
     * @throws Throwable
     */
    public function handle(array $company, string $subdomain, array $administrator): Tenant
    {
        $tenant = Tenant::create([
            ...$company,
            'status' => TenantStatus::Active,
            'tenancy_db_name' => Tenant::schemaNameFor($subdomain),
        ]);

        $password = $administrator['password'] ?? null;

        try {
            $tenant->domains()->create(['domain' => $subdomain]);

            // Los roles base ya se sembraron al crear el tenant (SeedTenantAccessControl).
            $admin = $tenant->run(function () use ($administrator, $password): User {
                $admin = User::forceCreate([
                    'name' => $administrator['name'],
                    'username' => $administrator['username'],
                    'email' => $administrator['email'],
                    'password' => $password ?? Str::password(40),
                    // Con contraseña definida en el panel la cuenta queda activa de una vez.
                    'email_verified_at' => $password !== null ? now() : null,
                ]);

                $admin->syncRoles([Role::administrator()]);

                return $admin;
            });

            $tenant->update(['initial_user_id' => $admin->getKey()]);
        } catch (Throwable $exception) {
            $tenant->delete();

            throw $exception;
        }

        if ($password === null) {
            $this->sendInvitation->handle($tenant, $admin);
        }

        return $tenant;
    }
}
