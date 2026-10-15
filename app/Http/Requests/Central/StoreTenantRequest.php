<?php

namespace App\Http\Requests\Central;

use App\Rules\Username;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreTenantRequest extends FormRequest
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
        return [
            ...TenantCompanyRules::rules(),
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_username' => ['required', 'string', new Username],
            // El correo es opcional, salvo que se le envíe la invitación.
            'admin_email' => ['nullable', 'required_if_accepted:send_invitation', 'string', 'lowercase', 'email', 'max:255'],
            // Con invitación el administrador define su contraseña; sin ella, se define aquí.
            'send_invitation' => ['required', 'boolean'],
            'admin_password' => ['exclude_if:send_invitation,true', 'required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array{name: string, username: string, email: string|null, password: string|null}
     */
    public function administrator(): array
    {
        return [
            'name' => $this->validated('admin_name'),
            'username' => $this->validated('admin_username'),
            'email' => $this->validated('admin_email'),
            'password' => $this->boolean('send_invitation') ? null : $this->validated('admin_password'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...TenantCompanyRules::attributes(),
            'admin_name' => 'nombre del administrador',
            'admin_username' => 'usuario del administrador',
            'admin_email' => 'correo del administrador',
            'admin_password' => 'contraseña del administrador',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...TenantCompanyRules::messages(),
            'admin_email.required_if_accepted' => 'El correo del administrador es obligatorio para enviarle la invitación.',
        ];
    }
}
