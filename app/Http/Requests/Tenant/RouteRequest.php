<?php

namespace App\Http\Requests\Tenant;

use App\Enums\RouteStatus;
use App\Enums\RouteType;
use App\Models\TransportRoute;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos de una ruta. Como en el formulario del legacy, todos son obligatorios. No puede haber
 * otra ruta con el mismo inicio y fin (sin distinguir mayúsculas ni espacios).
 */
class RouteRequest extends FormRequest
{
    /**
     * Se autoriza antes de validar, así quien no tiene permiso no recibe errores de validación.
     */
    public function authorize(): bool
    {
        $route = $this->route('route');

        return $route instanceof TransportRoute ? $this->user()->can('update', $route) : $this->user()->can('create', TransportRoute::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->only(['start_point', 'end_point', 'description']))
            ->map(fn ($value) => is_string($value) ? preg_replace('/\s+/', ' ', trim($value)) : $value)
            ->all());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start_point' => ['required', 'string', 'max:100'],
            'end_point' => ['required', 'string', 'max:100', $this->notRepeated()],
            'description' => ['required', 'string', 'max:2000'],
            'type' => ['required', Rule::enum(RouteType::class)],
            'status' => ['required', Rule::enum(RouteStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'start_point' => 'punto de inicio',
            'end_point' => 'punto de fin',
            'description' => 'descripción',
            'type' => 'tipo de ruta',
            'status' => 'estado',
        ];
    }

    private function notRepeated(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $current = $this->route('route');
            $duplicate = TransportRoute::findDuplicate(
                (string) $this->input('start_point'),
                (string) $value,
                $current instanceof TransportRoute ? $current->id : null,
            );

            if ($duplicate !== null) {
                $fail("Ya existe la ruta \"{$duplicate->name}\" con el mismo inicio y fin.");
            }
        };
    }
}
