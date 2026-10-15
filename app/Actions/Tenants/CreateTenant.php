<?php

namespace App\Actions\Tenants;

use App\Enums\TenantStatus;
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
     * @param  array{name: string, email: string, password?: string|null}  $administrator  sin password se envía invitación
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

            // Fase 1: aquí se siembran los roles base del tenant y se asigna el rol Administrador.
            $admin = $tenant->run(fn (): User => User::forceCreate([
                'name' => $administrator['name'],
                'email' => $administrator['email'],
                'password' => $password ?? Str::password(40),
                // Con contraseña definida en el panel la cuenta queda activa de una vez.
                'email_verified_at' => $password !== null ? now() : null,
            ]));

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
