<?php

namespace App\Http\Requests\Tenant;

use App\Enums\FuelType;
use App\Enums\VehicleClass;
use App\Enums\VehicleDocumentType;
use App\Models\Ally;
use App\Models\Supplier;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Datos de un vehículo, con las validaciones del formulario legacy: placa de 3 letras y 3 números
 * que no se repite, y obligatorios todos los datos salvo número interno, cilindraje y combustible.
 * El dueño es la empresa, un proveedor o un aliado; un usuario que pertenece a un tercero solo
 * registra vehículos a nombre de ese tercero.
 *
 * Incluye los documentos (como el formulario legacy): cada tipo que se llena registra o corrige su
 * documento vigente, con las mismas reglas que en la ficha; los que se dejan vacíos no se tocan.
 */
class VehicleRequest extends FormRequest
{
    /**
     * Se autoriza antes de validar, así quien no tiene permiso no recibe errores de validación.
     */
    public function authorize(): bool
    {
        $vehicle = $this->route('vehicle');

        return $vehicle instanceof Vehicle ? $this->user()->can('update', $vehicle) : $this->user()->can('create', Vehicle::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->only(['internal_number', 'registration_place', 'brand', 'line', 'engine_number', 'chassis_number', 'color']))
            ->map(fn ($value) => is_string($value) ? preg_replace('/\s+/', ' ', trim($value)) : $value)
            ->all());

        if (is_string($this->input('plate'))) {
            $this->merge(['plate' => mb_strtoupper((string) preg_replace('/\s+/', '', $this->input('plate')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $vehicle = $this->route('vehicle');
        $ownedByThirdParty = $this->user()->ally_id !== null || $this->user()->supplier_id !== null;

        return [
            'plate' => [
                'required', 'string', 'regex:/^[A-Z]{3}[0-9]{3}$/',
                Rule::unique('vehicles', 'plate')->ignore($vehicle instanceof Vehicle ? $vehicle->id : null),
            ],
            'internal_number' => ['nullable', 'string', 'max:50'],
            'current_mileage' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'registration_date' => ['required', 'date', 'before_or_equal:today'],
            'registration_place' => ['required', 'string', 'max:100'],
            'model_year' => ['required', 'integer', 'between:1950,'.(now()->year + 1)],
            'brand' => ['required', 'string', 'max:50'],
            'line' => ['required', 'string', 'max:50'],
            'vehicle_class' => ['required', Rule::enum(VehicleClass::class)],
            'engine_number' => ['required', 'string', 'max:50'],
            'chassis_number' => ['required', 'string', 'max:50'],
            'color' => ['required', 'string', 'max:50'],
            'displacement' => ['nullable', 'integer', 'min:1', 'max:99999'],
            'fuel_type' => ['nullable', Rule::enum(FuelType::class)],
            'owner_type' => $ownedByThirdParty ? ['nullable'] : ['required', Rule::in(['company', 'supplier', 'ally'])],
            'owner_id' => $ownedByThirdParty ? ['nullable'] : [
                'exclude_if:owner_type,company', 'required', 'integer',
                Rule::when($this->input('owner_type') === 'supplier', [Rule::exists('suppliers', 'id')]),
                Rule::when($this->input('owner_type') === 'ally', [Rule::exists('allies', 'id')]),
            ],
            ...$this->documentRules(),
        ];
    }

    /**
     * Reglas de cada documento: si se llena algo, los que vencen exigen la fecha de vencimiento y
     * la tarjeta de propiedad el número o el archivo (o que ya tenga uno).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    private function documentRules(): array
    {
        $rules = ['documents' => ['nullable', 'array']];

        foreach (VehicleDocumentType::cases() as $type) {
            $prefix = "documents.{$type->value}";
            $filled = $this->documentFilled($type);
            $rules["{$prefix}.number"] = ['nullable', 'string', 'max:50'];
            $rules["{$prefix}.issued_on"] = ['nullable', 'date', 'before_or_equal:today'];
            $rules["{$prefix}.file"] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.VehicleDocumentRequest::MAX_FILE_KB];

            if ($type->expires()) {
                $rules["{$prefix}.expires_on"] = [
                    Rule::requiredIf($filled), 'nullable', 'date',
                    ...(filled($this->input("{$prefix}.issued_on")) ? ["after_or_equal:{$prefix}.issued_on"] : []),
                ];
            } else {
                $rules["{$prefix}.expires_on"] = ['prohibited'];
                // En la validación en vivo (Precognition) los archivos no se envían: no se sabe si adjuntó uno.
                $rules["{$prefix}.number"][] = Rule::requiredIf($filled && ! $this->isPrecognitive() && ! $this->hasFile("{$prefix}.file") && ! $this->currentDocuments()->get($type->value)?->file_path);
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plate.regex' => 'La placa debe tener 3 letras seguidas de 3 números (ej: ABC123).',
            'plate.unique' => 'La placa ya está registrada en otro vehículo.',
            'owner_id.required' => 'Selecciona el :attribute dueño del vehículo.',
            'documents.property_card.number.required' => 'Escribe el número o adjunta el archivo de la tarjeta de propiedad.',
            'documents.*.file.max' => 'El archivo no puede pesar más de 10 MB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'plate' => 'placa',
            'internal_number' => 'número interno',
            'current_mileage' => 'kilometraje actual',
            'registration_date' => 'fecha de matrícula',
            'registration_place' => 'lugar de matrícula',
            'model_year' => 'modelo',
            'brand' => 'marca',
            'line' => 'línea',
            'vehicle_class' => 'clase de vehículo',
            'engine_number' => 'número de motor',
            'chassis_number' => 'número de chasis',
            'color' => 'color',
            'displacement' => 'cilindraje',
            'fuel_type' => 'combustible',
            'owner_type' => 'propietario',
            'owner_id' => $this->input('owner_type') === 'ally' ? 'aliado' : 'proveedor',
            ...collect(VehicleDocumentType::cases())->flatMap(fn (VehicleDocumentType $type): array => [
                "documents.{$type->value}.number" => mb_strtolower($type->fieldLabels()['number'])." ({$type->label()})",
                "documents.{$type->value}.issued_on" => mb_strtolower($type->fieldLabels()['issued_on'])." ({$type->label()})",
                "documents.{$type->value}.expires_on" => mb_strtolower($type->fieldLabels()['expires_on'])." ({$type->label()})",
                "documents.{$type->value}.file" => "archivo ({$type->label()})",
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function vehicleData(): array
    {
        return collect($this->validated())->except(['owner_type', 'owner_id', 'documents'])->all();
    }

    /**
     * Documentos que se llenaron, para registrarlos o corregirlos.
     *
     * @return array<string, array{type: VehicleDocumentType, data: array{number: string|null, issued_on: string|null, expires_on: string|null}, file: UploadedFile|null}>
     */
    public function documentsToSave(): array
    {
        $documents = [];

        foreach (VehicleDocumentType::cases() as $type) {
            if (! $this->documentFilled($type)) {
                continue;
            }

            $prefix = "documents.{$type->value}";
            $number = $this->validated("{$prefix}.number");
            $documents[$type->value] = [
                'type' => $type,
                'data' => [
                    'number' => filled($number) ? preg_replace('/\s+/', ' ', trim($number)) : null,
                    'issued_on' => $this->validated("{$prefix}.issued_on"),
                    'expires_on' => $type->expires() ? $this->validated("{$prefix}.expires_on") : null,
                ],
                'file' => $this->file("{$prefix}.file"),
            ];
        }

        return $documents;
    }

    private function documentFilled(VehicleDocumentType $type): bool
    {
        $prefix = "documents.{$type->value}";

        return filled($this->input("{$prefix}.number"))
            || filled($this->input("{$prefix}.issued_on"))
            || filled($this->input("{$prefix}.expires_on"))
            || $this->hasFile("{$prefix}.file");
    }

    /**
     * Documentos vigentes del vehículo que se edita, por tipo.
     *
     * @return Collection<string, VehicleDocument>
     */
    private function currentDocuments(): Collection
    {
        $vehicle = $this->route('vehicle');

        return $vehicle instanceof Vehicle
            ? $vehicle->currentDocuments()->get()->keyBy(fn (VehicleDocument $document): string => $document->type->value)
            : collect();
    }

    /**
     * Dueño elegido (null = la empresa). Un usuario de un aliado o de un proveedor no elige: el
     * vehículo queda a nombre de su tercero.
     */
    public function owner(): Supplier|Ally|null
    {
        $user = $this->user();

        return match (true) {
            $user->ally_id !== null => Ally::query()->findOrFail($user->ally_id),
            $user->supplier_id !== null => Supplier::query()->findOrFail($user->supplier_id),
            $this->validated('owner_type') === 'supplier' => Supplier::query()->findOrFail($this->validated('owner_id')),
            $this->validated('owner_type') === 'ally' => Ally::query()->findOrFail($this->validated('owner_id')),
            default => null,
        };
    }
}
