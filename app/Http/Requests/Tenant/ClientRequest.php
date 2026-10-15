<?php

namespace App\Http\Requests\Tenant;

use App\Enums\ClientType;
use App\Enums\IdentificationType;
use App\Models\Client;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos de un cliente, con las validaciones del "editar" del legacy aplicadas también al crear:
 * identificación de 6 a 20 dígitos y no repetida en ningún otro cliente, teléfono de 7 a 15
 * dígitos (se le quitan espacios, guiones y el +57). El DV es opcional (1 o 2 dígitos). El tipo
 * de cliente se elige al crear y no se cambia después; una persona no lleva razón social ni una
 * empresa nombres y apellidos.
 *
 * Si al editar no se cambia la identificación, no se vuelve a exigir que sea única: producción
 * tiene clientes repetidos que, si no, no se podrían editar nunca.
 */
class ClientRequest extends FormRequest
{
    /**
     * La autorización la hace el controlador con ClientPolicy.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $trimmed = collect($this->only([
            'identification', 'check_digit', 'branch_code', 'first_names', 'last_names',
            'business_name', 'trade_name', 'city', 'address', 'extension',
        ]))->map(fn ($value) => is_string($value) ? (preg_replace('/\s+/', ' ', trim($value)) ?: null) : $value);

        $this->merge([...$trimmed->all(), 'phone' => $this->normalizedPhone()]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => [Rule::requiredIf($this->current() === null), Rule::enum(ClientType::class)],
            'identification_type' => ['required', Rule::enum(IdentificationType::class)],
            'identification' => ['required', 'regex:/^\d{6,20}$/', $this->identificationNotTaken()],
            'check_digit' => ['nullable', 'regex:/^\d{1,2}$/'],
            'branch_code' => ['nullable', 'string', 'max:20'],
            'first_names' => [Rule::requiredIf($this->clientType() === ClientType::Person), 'nullable', 'string', 'max:100'],
            'last_names' => [Rule::requiredIf($this->clientType() === ClientType::Person), 'nullable', 'string', 'max:100'],
            'business_name' => [Rule::requiredIf($this->clientType() === ClientType::Company), 'nullable', 'string', 'max:200'],
            'trade_name' => ['nullable', 'string', 'max:200'],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:200'],
            'phone' => ['required', 'regex:/^\d{7,15}$/'],
            'extension' => ['nullable', 'string', 'max:10'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'identification.regex' => 'La identificación debe contener solo números (6 a 20 dígitos).',
            'phone.regex' => 'El teléfono debe contener solo números (7 a 15 dígitos).',
            'check_digit.regex' => 'El DV debe tener 1 o 2 dígitos.',
            'first_names.required' => 'Los nombres son obligatorios para una persona.',
            'last_names.required' => 'Los apellidos son obligatorios para una persona.',
            'business_name.required' => 'La razón social es obligatoria para una empresa.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'tipo de cliente',
            'identification_type' => 'tipo de identificación',
            'identification' => 'identificación',
            'check_digit' => 'DV',
            'branch_code' => 'código de sucursal',
            'first_names' => 'nombres',
            'last_names' => 'apellidos',
            'business_name' => 'razón social',
            'trade_name' => 'nombre comercial',
            'city' => 'ciudad',
            'address' => 'dirección',
            'phone' => 'teléfono',
            'extension' => 'extensión',
        ];
    }

    /**
     * Datos a guardar: los campos del otro tipo de cliente quedan vacíos.
     *
     * @return array<string, mixed>
     */
    public function clientData(): array
    {
        $data = collect($this->validated())->except('type')->all();
        $isPerson = $this->clientType() === ClientType::Person;

        return [
            ...$data,
            'first_names' => $isPerson ? $data['first_names'] : null,
            'last_names' => $isPerson ? $data['last_names'] : null,
            'business_name' => $isPerson ? null : $data['business_name'],
            'trade_name' => $isPerson ? null : ($data['trade_name'] ?? null),
            'check_digit' => $data['check_digit'] ?? null,
        ];
    }

    public function clientType(): ?ClientType
    {
        return $this->current()?->type ?? ClientType::tryFrom((string) $this->input('type'));
    }

    private function current(): ?Client
    {
        $client = $this->route('client');

        return $client instanceof Client ? $client : null;
    }

    private function identificationNotTaken(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $current = $this->current();

            if ($current !== null && $current->identification === $value) {
                return;
            }

            $taken = Client::query()
                ->where('identification', $value)
                ->when($current, fn ($query) => $query->whereKeyNot($current->id))
                ->exists();

            if ($taken) {
                $fail('La identificación ya está registrada en otro cliente.');
            }
        };
    }

    /**
     * Solo dígitos; sin el indicativo 57 cuando viene delante de un celular de 10 dígitos.
     */
    private function normalizedPhone(): mixed
    {
        $phone = $this->input('phone');

        if (! is_string($phone)) {
            return $phone;
        }

        $digits = preg_replace('/\D/', '', $phone);

        return strlen($digits) === 12 && str_starts_with($digits, '57') ? substr($digits, 2) : $digits;
    }
}
