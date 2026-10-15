<?php

namespace App\Http\Requests\Tenant;

use App\Models\Ally;
use App\Models\Supplier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos de un aliado o un proveedor (mismos campos en los dos módulos, como en el legacy). Solo
 * el nombre es obligatorio, y ni el nombre ni el NIT pueden repetirse dentro del mismo módulo.
 * El NIT se guarda sin espacios al inicio ni al final.
 */
abstract class ThirdPartyRequest extends FormRequest
{
    /**
     * @return class-string<Ally|Supplier>
     */
    abstract protected function model(): string;

    /**
     * Registro que se está editando (null al crear).
     */
    abstract protected function current(): Ally|Supplier|null;

    /**
     * La autorización la hace el controlador con la Policy del módulo.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? preg_replace('/\s+/', ' ', trim($this->name)) : $this->name,
            'nit' => is_string($this->nit) ? (trim($this->nit) ?: null) : $this->nit,
            'phone' => is_string($this->phone) ? (trim($this->phone) ?: null) : $this->phone,
            'address' => is_string($this->address) ? (trim($this->address) ?: null) : $this->address,
            'email' => is_string($this->email) ? (mb_strtolower(trim($this->email)) ?: null) : $this->email,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', $this->notRepeated('name', 'Ya existe un registro con este nombre.')],
            'nit' => ['nullable', 'string', 'max:50', $this->notRepeated('nit', 'Ya existe un registro con este NIT.')],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'nit' => 'NIT',
            'phone' => 'teléfono',
            'address' => 'dirección',
            'email' => 'correo',
        ];
    }

    /**
     * Nombre (sin distinguir mayúsculas) o NIT ya usados por otro registro del mismo módulo.
     */
    private function notRepeated(string $column, string $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($column, $message): void {
            $query = $this->model()::query()
                ->when($this->current(), fn ($query, $current) => $query->whereKeyNot($current->getKey()));

            $exists = $column === 'name'
                ? $query->whereRaw('lower(name) = ?', [mb_strtolower((string) $value)])->exists()
                : $query->where('nit', $value)->exists();

            if ($exists) {
                $fail($message);
            }
        };
    }
}
