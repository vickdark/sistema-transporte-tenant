<?php

namespace App\Http\Tables\Tenant;

use App\Enums\Permission;
use App\Http\Tables\DataTable;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Listado de roles de la empresa con cuántos permisos y usuarios tiene cada uno.
 *
 * @extends DataTable<Role>
 */
class RolesTable extends DataTable
{
    public function title(): string
    {
        return 'Roles y permisos';
    }

    protected function query(): Builder
    {
        $search = $this->search();

        return Role::query()
            ->withCount(['users', 'permissions'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('description', "%{$search}%")));
    }

    protected function sortable(): array
    {
        return ['name' => 'name', 'permissions_count' => 'permissions_count', 'users_count' => 'users_count'];
    }

    protected function defaultSort(): array
    {
        return ['name', 'asc'];
    }

    /**
     * Ordenado por nombre, el Administrador (protegido) va siempre primero.
     */
    protected function sortedQuery(): Builder
    {
        [$sort] = $this->sort();

        if ($sort !== 'name') {
            return parent::sortedQuery();
        }

        return parent::sortedQuery()->reorder()
            ->orderByDesc('is_protected')
            ->orderBy('name', $this->sort()[1])
            ->orderBy('id');
    }

    /**
     * @param  Role  $model
     */
    protected function toRow(Model $model): array
    {
        $viewer = $this->request->user();

        return [
            'id' => $model->id,
            'name' => $model->name,
            'description' => $model->description,
            'is_protected' => $model->is_protected,
            'users_count' => $model->users_count,
            'permissions_count' => $model->permissions_count,
            'can' => [
                'update' => $viewer->can('update', $model),
                'delete' => $viewer->can('delete', $model),
            ],
        ];
    }

    protected function exportColumns(): array
    {
        $total = count(Permission::cases());

        return [
            'Rol' => fn (Role $role) => $role->name,
            'Descripción' => fn (Role $role) => $role->is_protected ? 'Tiene todos los permisos y no se puede modificar.' : $role->description,
            'Permisos' => fn (Role $role) => "{$role->permissions_count} / {$total}",
            'Usuarios' => fn (Role $role) => $role->users_count,
        ];
    }
}
