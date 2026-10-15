<?php

namespace App\Http\Requests\Tenant;

use App\Models\Role;
use App\Models\User;
use App\Rules\Username;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Crear o editar un usuario de la empresa (la autorización la hace UserPolicy en el controlador).
 * El acceso se resuelve con una invitación por correo o con una contraseña definida aquí;
 * al editar, la contraseña es opcional.
 */
class UserRequest extends FormRequest
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
        /** @var User|null $user */
        $user = $this->route('user');
        $isCreating = $user === null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', new Username, Rule::unique(User::class)->ignore($user)],
            'email' => [
                'nullable',
                'required_if_accepted:send_invitation',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user),
            ],
            'role_id' => ['required', 'integer', Rule::exists(Role::class, 'id')],
            'send_invitation' => ['required', 'boolean'],
            'password' => [
                'exclude_if:send_invitation,true',
                $isCreating ? 'required' : 'nullable',
                'string',
                'confirmed',
                Password::defaults(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'username' => 'usuario',
            'email' => 'correo',
            'role_id' => 'rol',
            'password' => 'contraseña',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required_if_accepted' => 'El correo es obligatorio para enviar la invitación.',
        ];
    }

    public function role(): Role
    {
        return Role::query()->findOrFail($this->validated('role_id'));
    }

    /**
     * Contraseña definida en el formulario (null si se envía invitación o se dejó vacía).
     */
    public function password(): ?string
    {
        return $this->boolean('send_invitation') ? null : $this->validated('password');
    }
}
