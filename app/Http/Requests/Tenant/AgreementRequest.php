<?php

namespace App\Http\Requests\Tenant;

use App\Models\Agreement;
use App\Models\Vehicle;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos de un convenio, como en el legacy: NIT que no se repite, razón social y al menos un
 * vehículo activo. Un vehículo está como mucho en un convenio; los que ya están en este se pueden
 * conservar aunque se hayan inhabilitado.
 */
class AgreementRequest extends FormRequest
{
    /**
     * Se autoriza antes de validar, así quien no tiene permiso no recibe errores de validación.
     */
    public function authorize(): bool
    {
        $agreement = $this->agreement();

        return $agreement ? $this->user()->can('update', $agreement) : $this->user()->can('create', Agreement::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nit' => is_string($this->input('nit')) ? preg_replace('/\s+/', '', $this->input('nit')) : $this->input('nit'),
            'business_name' => is_string($this->input('business_name')) ? preg_replace('/\s+/', ' ', trim($this->input('business_name'))) : $this->input('business_name'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nit' => ['required', 'string', 'max:20', Rule::unique('agreements', 'nit')->ignore($this->agreement()?->id)],
            'business_name' => ['required', 'string', 'max:255'],
            'vehicles' => ['required', 'array', 'min:1'],
            'vehicles.*' => ['integer', 'distinct', $this->availableVehicle()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nit.unique' => 'Ya existe un convenio con este NIT.',
            'vehicles.required' => 'Selecciona al menos un vehículo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nit' => 'NIT',
            'business_name' => 'razón social',
            'vehicles' => 'vehículos',
        ];
    }

    public function agreement(): ?Agreement
    {
        $agreement = $this->route('agreement');

        return $agreement instanceof Agreement ? $agreement : null;
    }

    private function availableVehicle(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $vehicle = Vehicle::query()->with('agreement')->find($value);
            $inThisAgreement = $vehicle !== null && $this->agreement() !== null && $vehicle->agreement_id === $this->agreement()->id;

            if ($vehicle === null || (! $vehicle->is_active && ! $inThisAgreement)) {
                $fail('Algún vehículo seleccionado no existe o está inactivo.');
            } elseif ($vehicle->agreement !== null && ! $inThisAgreement) {
                $fail("El vehículo {$vehicle->plate} ya está en el convenio con {$vehicle->agreement->business_name}.");
            }
        };
    }
}
