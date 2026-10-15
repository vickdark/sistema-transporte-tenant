<?php

namespace App\Http\Requests\Central;

use App\Models\Tenant;
use App\Models\User;
use App\Rules\Username;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateTenantInitialUserRequest extends FormRequest
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
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');
        $initialUser = $tenant->initialUser();

        // Los usuarios viven en el schema de la empresa: la unicidad se revisa allí.
        $uniqueInTenant = fn (string $column, string $message): Closure => function (string $attribute, mixed $value, Closure $fail) use ($tenant, $initialUser, $column, $message): void {
            $taken = $tenant->run(fn (): bool => User::query()
                ->where($column, $value)
                ->when($initialUser, fn ($query) => $query->whereKeyNot($initialUser->getKey()))
                ->exists());

            if ($taken) {
                $fail($message);
            }
        };

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', new Username, $uniqueInTenant('username', 'Otro usuario de esta empresa ya usa este usuario.')],
            // Opcional, salvo que se le envíe la invitación.
            'email' => [
                'nullable',
                'required_if_accepted:send_invitation',
                'string',
                'lowercase',
                'email',
                'max:255',
                $uniqueInTenant('email', 'Otro usuario de esta empresa ya usa este correo.'),
            ],
            // O se envía una invitación (el usuario define su contraseña) o se cambia aquí; ambas opcionales.
            'send_invitation' => ['required', 'boolean'],
            'password' => ['exclude_if:send_invitation,true', 'nullable', 'string', 'confirmed', Password::defaults()],
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
}
