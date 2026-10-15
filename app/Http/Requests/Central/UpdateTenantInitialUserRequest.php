<?php

namespace App\Http\Requests\Central;

use App\Models\Tenant;
use App\Models\User;
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

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                // Los usuarios viven en el schema de la empresa: la unicidad se revisa allí.
                function (string $attribute, mixed $value, Closure $fail) use ($tenant, $initialUser): void {
                    $taken = $tenant->run(fn (): bool => User::query()
                        ->where('email', $value)
                        ->when($initialUser, fn ($query) => $query->whereKeyNot($initialUser->getKey()))
                        ->exists());

                    if ($taken) {
                        $fail('Otro usuario de esta empresa ya usa este correo.');
                    }
                },
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
            'email' => 'correo',
            'password' => 'contraseña',
        ];
    }
}
