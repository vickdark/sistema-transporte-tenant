<?php

namespace App\Http\Requests\Tenant;

use App\Models\CompanyProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos de la empresa para sus documentos. Los tres datos de habilitación forman el prefijo del
 * número del FUEC, por eso tienen formato fijo: dirección territorial (3 dígitos), resolución
 * (hasta 4 dígitos, se guarda con ceros a la izquierda) y año (2 dígitos).
 */
class CompanyProfileRequest extends FormRequest
{
    /**
     * Se autoriza antes de validar, así quien no tiene permiso no recibe errores de validación.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', CompanyProfile::current());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'service_type' => ['required', 'string', 'max:150'],
            'territorial_code' => ['required', 'digits:3'],
            'habilitation_resolution' => ['required', 'digits_between:1,4'],
            'habilitation_year' => ['required', 'digits:2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'address' => 'dirección',
            'phone' => 'teléfono',
            'email' => 'correo',
            'service_type' => 'tipo de servicio',
            'territorial_code' => 'dirección territorial',
            'habilitation_resolution' => 'resolución de habilitación',
            'habilitation_year' => 'año de habilitación',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function profile(): array
    {
        return [
            ...$this->validated(),
            'email' => mb_strtolower(trim($this->validated('email'))),
            'habilitation_resolution' => str_pad($this->validated('habilitation_resolution'), 4, '0', STR_PAD_LEFT),
        ];
    }
}
