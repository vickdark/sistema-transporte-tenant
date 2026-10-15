<?php

namespace App\Http\Requests\Tenant;

use App\Actions\Contracts\ContractOwner;
use App\Enums\ClientType;
use App\Enums\ContractPurpose;
use App\Models\Client;
use App\Models\Contract;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Datos de un contrato, con las reglas del legacy:
 *
 * - Cliente activo y del mismo dueño (la empresa o el aliado con el que se trabaja). No se
 *   cambia al editar.
 * - Inicio ≤ fin, y el fin no pasa del 31 de diciembre del año de inicio.
 * - Un cliente no tiene dos contratos a la vez: las fechas no pueden cruzarse con otro contrato
 *   suyo (el legacy impedía crear otro mientras hubiera uno vigente).
 * - Datos del responsable: obligatorios si el cliente es una empresa. Si es una persona, el
 *   responsable es el mismo cliente y se toman sus datos (como el legacy, que los pre-llenaba);
 *   si el cliente no tiene correo (los migrados), se pide aquí.
 */
class ContractRequest extends FormRequest
{
    /**
     * Se autoriza antes de validar, así quien no tiene permiso no recibe errores de validación.
     */
    public function authorize(): bool
    {
        return $this->current() !== null
            ? $this->user()->can('update', $this->current())
            : $this->user()->can('create', [Contract::class, $this->owner()->kind]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->only(['responsible_identification', 'responsible_name', 'responsible_email', 'responsible_phone']))
            ->map(fn ($value) => is_string($value) ? (preg_replace('/\s+/', ' ', trim($value)) ?: null) : $value)
            ->all());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => [Rule::requiredIf($this->current() === null), 'integer', $this->clientOfOwner()],
            'purpose' => ['required', Rule::enum(ContractPurpose::class)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on', $this->withinStartYear(), $this->notOverlapping()],
            'responsible_identification' => [Rule::requiredIf($this->isCompanyClient()), 'nullable', 'string', 'max:20'],
            'responsible_name' => [Rule::requiredIf($this->isCompanyClient()), 'nullable', 'string', 'max:150'],
            'responsible_email' => [Rule::requiredIf($this->isCompanyClient() || $this->isPersonWithoutEmail()), 'nullable', 'email', 'max:100'],
            'responsible_phone' => [Rule::requiredIf($this->isCompanyClient()), 'nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['ends_on.after_or_equal' => 'La fecha de fin no puede ser anterior a la fecha de inicio.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'client_id' => 'cliente',
            'purpose' => 'objeto del contrato',
            'starts_on' => 'fecha de inicio',
            'ends_on' => 'fecha de fin',
            'responsible_identification' => 'identificación del responsable',
            'responsible_name' => 'nombre del responsable',
            'responsible_email' => 'correo del responsable',
            'responsible_phone' => 'teléfono del responsable',
        ];
    }

    /**
     * Datos a guardar. Si el cliente es una persona, el responsable es el mismo cliente.
     *
     * @return array<string, mixed>
     */
    public function contractData(): array
    {
        $client = $this->client();

        if ($client === null || $client->type === ClientType::Company) {
            return $this->validated();
        }

        return [
            ...$this->safe()->except(['responsible_identification', 'responsible_name', 'responsible_email', 'responsible_phone']),
            'responsible_identification' => $client->identification,
            'responsible_name' => $client->displayName(),
            'responsible_email' => $client->email ?? mb_strtolower((string) $this->validated('responsible_email')),
            'responsible_phone' => $client->phone,
        ];
    }

    public function client(): ?Client
    {
        return $this->current()?->client ?? Client::query()->find((int) $this->input('client_id'));
    }

    private function isCompanyClient(): bool
    {
        return $this->client()?->type === ClientType::Company;
    }

    /**
     * Cliente persona sin correo (p. ej. migrado): el correo del responsable se pide en el contrato.
     */
    private function isPersonWithoutEmail(): bool
    {
        $client = $this->client();

        return $client?->type === ClientType::Person && $client->email === null;
    }

    public function clientId(): int
    {
        return $this->current()?->client_id ?? (int) $this->validated('client_id');
    }

    private function current(): ?Contract
    {
        $contract = $this->route('contract');

        return $contract instanceof Contract ? $contract : null;
    }

    private function owner(): ContractOwner
    {
        return ContractOwner::fromRequest($this);
    }

    private function clientOfOwner(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($this->current() === null && ! $this->owner()->clients()->whereKey($value)->exists()) {
                $fail('El cliente no existe, está inactivo o no pertenece a este listado de contratos.');
            }
        };
    }

    private function withinStartYear(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $start = strtotime((string) $this->input('starts_on'));

            if ($start === false || strtotime((string) $value) === false) {
                return;
            }

            $lastDay = date('Y', $start).'-12-31';

            if (date('Y-m-d', strtotime((string) $value)) > $lastDay) {
                $fail('La fecha de fin no puede superar el 31 de diciembre del año de la fecha de inicio ('.Carbon::parse($lastDay)->format('d/m/Y').').');
            }
        };
    }

    private function notOverlapping(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $clientId = $this->current()?->client_id ?? (int) $this->input('client_id');

            if (! $clientId || strtotime((string) $this->input('starts_on')) === false || strtotime((string) $value) === false) {
                return;
            }

            $other = Contract::query()
                ->where('client_id', $clientId)
                ->where('kind', $this->owner()->kind)
                ->when($this->current(), fn ($query, Contract $current) => $query->whereKeyNot($current->id))
                ->whereDate('starts_on', '<=', $value)
                ->whereDate('ends_on', '>=', $this->input('starts_on'))
                ->orderBy('starts_on')
                ->first();

            if ($other !== null) {
                $fail("El cliente ya tiene el contrato {$other->formattedNumber()} del {$other->starts_on->format('d/m/Y')} al {$other->ends_on->format('d/m/Y')}: las fechas no pueden cruzarse.");
            }
        };
    }
}
