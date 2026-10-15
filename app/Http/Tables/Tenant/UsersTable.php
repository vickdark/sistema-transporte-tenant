<?php

namespace App\Http\Tables\Tenant;

use App\Http\Tables\DataTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Listado de usuarios de la empresa: búsqueda por nombre, usuario o correo y filtro por rol.
 *
 * @extends DataTable<User>
 */
class UsersTable extends DataTable
{
    public function title(): string
    {
        return 'Usuarios';
    }

    protected function query(): Builder
    {
        $search = $this->search();
        $roleId = $this->roleId();

        return User::query()
            ->with('roles:id,name,is_protected')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('username', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")))
            ->when($roleId, fn ($query) => $query->whereHas('roles', fn ($query) => $query->whereKey($roleId)));
    }

    protected function sortable(): array
    {
        return ['name' => 'name', 'username' => 'username', 'email' => 'email'];
    }

    protected function defaultSort(): array
    {
        return ['name', 'asc'];
    }

    /**
     * @param  User  $model
     */
    protected function toRow(Model $model): array
    {
        $role = $model->roles->first();
        $viewer = $this->request->user();

        return [
            'id' => $model->id,
            'name' => $model->name,
            'username' => $model->username,
            'email' => $model->email,
            'role' => $role ? ['id' => $role->id, 'name' => $role->name, 'is_protected' => $role->is_protected] : null,
            'invitation_pending' => $model->hasPendingInvitation(),
            'can' => [
                'update' => $viewer->can('update', $model),
                'delete' => $viewer->can('delete', $model),
            ],
        ];
    }

    protected function exportColumns(): array
    {
        return [
            'Nombre' => fn (User $user) => $user->name,
            'Usuario' => fn (User $user) => $user->username,
            'Correo' => fn (User $user) => $user->email,
            'Rol' => fn (User $user) => $user->roles->first()?->name ?? 'Sin rol',
            'Estado' => fn (User $user) => $user->hasPendingInvitation() ? 'Invitación pendiente' : 'Activo',
        ];
    }

    protected function filters(): array
    {
        return ['role' => $this->roleId()];
    }

    protected function filterSummary(): array
    {
        $role = $this->roleId() ? Role::query()->find($this->roleId())?->name : null;

        return [...parent::filterSummary(), ...($role ? ["Rol: {$role}"] : [])];
    }

    private function roleId(): ?int
    {
        return $this->request->integer('role') ?: null;
    }
}
