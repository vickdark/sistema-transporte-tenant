<?php

namespace App\Actions\Users;

use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Única forma de asignar el rol de un usuario: reemplaza el que tenga (un rol por usuario,
 * reforzado también por un índice único en model_has_roles) y nunca deja a la empresa sin un
 * Administrador. Corre con la tenancy de la empresa inicializada.
 */
class AssignUserRole
{
    /**
     * @throws ValidationException
     */
    public function handle(User $user, Role $role): void
    {
        if (! $role->is_protected) {
            static::ensureIsNotTheLastAdministrator($user, 'role');
        }

        $user->syncRoles([$role]);
    }

    /**
     * Impide quitarle el rol Administrador (o borrar) al único usuario que lo tiene.
     *
     * @throws ValidationException
     */
    public static function ensureIsNotTheLastAdministrator(User $user, string $errorKey): void
    {
        $administrator = Role::administrator();

        if ($user->hasRole($administrator) && $administrator->users()->count() === 1) {
            throw ValidationException::withMessages([
                $errorKey => 'La empresa debe conservar al menos un usuario con el rol Administrador.',
            ]);
        }
    }
}
