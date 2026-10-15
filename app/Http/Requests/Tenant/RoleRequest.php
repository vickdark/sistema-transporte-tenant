<?php

namespace App\Http\Requests\Tenant;

use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crear o editar un rol con su matriz de permisos (la autorización la hace RolePolicy).
 */
class RoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Role|null $role */
        $role = $this->route('role');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique(Role::class, 'name')->ignore($role)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(Permission::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'description' => 'descripción',
            'permissions' => 'permisos',
        ];
    }
}
