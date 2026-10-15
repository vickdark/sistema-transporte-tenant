<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\RoleRequest;
use App\Http\Tables\Tenant\RolesTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Roles de la empresa y su matriz de permisos. El Administrador se muestra pero no se edita.
 */
class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Role::class);

        $table = new RolesTable($request);

        return Inertia::render('roles/Index', [
            'roles' => $table->rows(),
            'query' => $table->state(),
            'totalPermissions' => count(Permission::cases()),
            'can' => ['create' => $request->user()->can('create', Role::class)],
        ]);
    }

    /**
     * Export the filtered list (same search and order as the screen) to Excel or PDF.
     */
    public function export(Request $request, string $format): SymfonyResponse
    {
        Gate::authorize('viewAny', Role::class);

        return (new RolesTable($request))->export($format);
    }

    public function create(): Response
    {
        Gate::authorize('create', Role::class);

        return Inertia::render('roles/Create', [
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        Gate::authorize('create', Role::class);

        $role = DB::transaction(function () use ($request): Role {
            $role = Role::create([
                'name' => $request->validated('name'),
                'description' => $request->validated('description'),
                'guard_name' => User::GUARD,
            ]);
            $role->syncPermissions($request->validated('permissions'));

            return $role;
        });

        return to_route('roles.index')->with('success', "Rol {$role->name} creado.");
    }

    public function edit(Role $role): Response
    {
        Gate::authorize('update', $role);

        return Inertia::render('roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'permissions' => $role->permissions->pluck('name')->values(),
                'users_count' => $role->users()->count(),
            ],
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        Gate::authorize('update', $role);

        DB::transaction(function () use ($request, $role): void {
            $role->update($request->safe()->only(['name', 'description']));
            $role->syncPermissions($request->validated('permissions'));
        });

        return to_route('roles.index')->with('success', "Rol {$role->name} actualizado.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        Gate::authorize('delete', $role);

        $usersCount = $role->users()->count();

        if ($usersCount > 0) {
            return back()->withErrors(['role' => "No se puede eliminar {$role->name}: lo tienen {$usersCount} usuario(s). Asígnales otro rol primero."]);
        }

        $role->delete();

        return back()->with('success', "Rol {$role->name} eliminado.");
    }

    /**
     * Catálogo agrupado por módulo para la matriz de permisos.
     *
     * @return list<array{module: string, permissions: list<array{name: string, label: string}>}>
     */
    private function permissionGroups(): array
    {
        return collect(Permission::cases())
            ->groupBy(fn (Permission $permission): string => $permission->module())
            ->map(fn ($permissions, string $module): array => [
                'module' => $module,
                'permissions' => $permissions
                    ->map(fn (Permission $permission): array => ['name' => $permission->value, 'label' => $permission->label()])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
