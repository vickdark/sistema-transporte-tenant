<?php

namespace App\Http\Controllers\Central;

use App\Actions\Tenants\CreateTenant;
use App\Actions\Tenants\DeleteTenant;
use App\Actions\Tenants\RegisterLocalTenantHost;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\DestroyTenantRequest;
use App\Http\Requests\Central\StoreTenantRequest;
use App\Http\Requests\Central\UpdateTenantRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    /**
     * List the tenants, optionally filtered by name/NIT and status.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();
        $status = TenantStatus::tryFrom($request->string('status')->value());

        $tenants = Tenant::query()
            ->with('subdomain')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('nit', "%{$search}%")))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Tenant $tenant): array => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'nit' => $tenant->nit,
                'status' => $tenant->status,
                'status_label' => $tenant->status->label(),
                'subdomain' => $tenant->subdomain?->domain,
                'created_at' => $tenant->created_at?->toDateString(),
            ]);

        return Inertia::render('central/tenants/Index', [
            'tenants' => $tenants,
            'filters' => ['search' => $search, 'status' => $status?->value],
            'statuses' => $this->statusOptions(),
        ]);
    }

    /**
     * Show the form to create a tenant.
     */
    public function create(): Response
    {
        return Inertia::render('central/tenants/Create', [
            'tenantBaseDomain' => config('tenancy.tenant_base_domain'),
        ]);
    }

    /**
     * Store a new tenant with its subdomain and first administrator.
     */
    public function store(StoreTenantRequest $request, CreateTenant $createTenant, RegisterLocalTenantHost $registerLocalHost): RedirectResponse
    {
        $tenant = $createTenant->handle(
            company: $request->safe()->only(['name', 'nit', 'email', 'phone', 'address', 'city']),
            subdomain: $request->validated('subdomain'),
            administrator: $request->administrator(),
        );

        $response = to_route('central.tenants.show', $tenant)->with('success', $request->boolean('send_invitation')
            ? 'Empresa creada. Se envió la invitación al administrador.'
            : 'Empresa creada. El administrador ya puede ingresar con la contraseña definida.');

        return $this->withLocalHostWarning($response, $registerLocalHost->handle($request->validated('subdomain')), $tenant);
    }

    /**
     * Show a tenant.
     */
    public function show(Tenant $tenant): Response
    {
        $initialUser = $tenant->initialUser();

        return Inertia::render('central/tenants/Show', [
            'initialUser' => $initialUser ? [
                'name' => $initialUser->name,
                'email' => $initialUser->email,
                'invitation_pending' => $initialUser->hasPendingInvitation(),
                'invited_at' => $initialUser->invited_at?->toDateTimeString(),
            ] : null,
            'tenant' => [
                ...$this->tenantData($tenant),
                'status_label' => $tenant->status->label(),
                'suspended_at' => $tenant->suspended_at?->toDateTimeString(),
                'users_count' => $tenant->usersCount(),
                'created_at' => $tenant->created_at?->toDateTimeString(),
                'url' => $tenant->subdomain ? $tenant->url() : null,
            ],
        ]);
    }

    /**
     * Show the form to edit a tenant.
     */
    public function edit(Tenant $tenant): Response
    {
        return Inertia::render('central/tenants/Edit', [
            'tenant' => $this->tenantData($tenant),
            'tenantBaseDomain' => config('tenancy.tenant_base_domain'),
        ]);
    }

    /**
     * Update a tenant's company data and subdomain.
     */
    public function update(UpdateTenantRequest $request, Tenant $tenant, RegisterLocalTenantHost $registerLocalHost): RedirectResponse
    {
        $previousSubdomain = $tenant->subdomain?->domain;
        $subdomain = $request->validated('subdomain');

        // El schema sigue al subdominio. ALTER SCHEMA es transaccional en PostgreSQL: si algo
        // falla, el nombre del schema y el subdominio vuelven juntos a como estaban.
        DB::transaction(function () use ($request, $tenant, $subdomain): void {
            $tenant->update($request->safe()->except('subdomain'));
            $tenant->domains()->updateOrCreate([], ['domain' => $subdomain]);
            $tenant->renameSchemaFor($subdomain);
        });

        $response = to_route('central.tenants.show', $tenant)->with('success', 'Datos de la empresa actualizados.');

        if ($subdomain === $previousSubdomain) {
            return $response;
        }

        return $this->withLocalHostWarning($response, $registerLocalHost->handle($subdomain, $previousSubdomain), $tenant);
    }

    /**
     * Delete a tenant permanently, including its schema with all of its data.
     */
    public function destroy(DestroyTenantRequest $request, Tenant $tenant, DeleteTenant $deleteTenant): RedirectResponse
    {
        $name = $tenant->name;

        $deleteTenant->handle($tenant);

        return to_route('central.tenants.index')->with('success', "Empresa {$name} eliminada con todos sus datos.");
    }

    /**
     * Si Herd no pudo registrar el sitio local, avisa con el comando a ejecutar a mano.
     */
    private function withLocalHostWarning(RedirectResponse $response, bool $registered, Tenant $tenant): RedirectResponse
    {
        if ($registered) {
            return $response;
        }

        $subdomain = $tenant->refresh()->subdomain->domain;

        return $response->with('warning', "No se pudo registrar {$tenant->host()} en Herd. Ejecuta `herd link {$subdomain}` en la carpeta del proyecto.");
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantData(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'nit' => $tenant->nit,
            'status' => $tenant->status,
            'email' => $tenant->email,
            'phone' => $tenant->phone,
            'address' => $tenant->address,
            'city' => $tenant->city,
            'subdomain' => $tenant->subdomain?->domain,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (TenantStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            TenantStatus::cases(),
        );
    }
}
