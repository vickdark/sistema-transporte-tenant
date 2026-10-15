<?php

namespace App\Http\Requests\Tenant;

use App\Models\Part;
use App\Models\Vehicle;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Nueva parte del catálogo, como en el legacy: nombre que no se repite (sin distinguir mayúsculas
 * ni espacios), kilometraje entre mantenimientos mayor que cero y al menos un vehículo activo al
 * que se aplica.
 */
class PartRequest extends FormRequest
{
    /**
     * Se autoriza antes de validar, así quien no tiene permiso no recibe errores de validación.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage', Part::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => Part::clean($this->input('name'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'regex:/^[\pL\pN ]+$/u', $this->notRepeated()],
            'maintenance_interval_km' => ['required', 'integer', 'min:1', 'max:9999999'],
            'vehicles' => ['required', 'array', 'min:1'],
            'vehicles.*' => [
                'integer',
                Rule::exists('vehicles', 'id')->where('is_active', true),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'El nombre de la parte solo puede tener letras, números y espacios.',
            'vehicles.required' => 'Selecciona al menos un vehículo.',
            'vehicles.*.exists' => 'Algún vehículo seleccionado no existe o está inactivo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre de la parte',
            'maintenance_interval_km' => 'kilometraje entre mantenimientos',
            'vehicles' => 'vehículos',
        ];
    }

    /**
     * Vehículos elegidos que el usuario puede ver.
     *
     * @return list<int>
     */
    public function vehicleIds(): array
    {
        return Vehicle::query()
            ->visibleTo($this->user())
            ->whereKey($this->validated('vehicles'))
            ->pluck('id')
            ->all();
    }

    private function notRepeated(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $existing = Part::findByName((string) $value);

            if ($existing !== null) {
                $fail("La parte \"{$existing->name}\" ya existe: agrégala a más vehículos desde su ficha.");
            }
        };
    }
}
