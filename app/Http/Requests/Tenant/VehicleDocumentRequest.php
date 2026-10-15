<?php

namespace App\Http\Requests\Tenant;

use App\Enums\VehicleDocumentType;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Documento vigente de un vehículo: registrarlo o corregirlo (sin historial), o renovarlo (el
 * anterior pasa al historial). Los documentos que vencen exigen la fecha de vencimiento; la
 * tarjeta de propiedad, el número o el archivo. El archivo es un PDF o una imagen.
 */
class VehicleDocumentRequest extends FormRequest
{
    public const MAX_FILE_KB = 10240;

    /**
     * Se autoriza antes de validar, así quien no tiene permiso no recibe errores de validación.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->vehicle());
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('number'))) {
            $this->merge(['number' => preg_replace('/\s+/', ' ', trim($this->input('number')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->documentType();
        $current = $this->renewing() ? $this->currentDocument() : null;

        return [
            'number' => $type->expires() ? ['nullable', 'string', 'max:50'] : ['required_without:file', 'nullable', 'string', 'max:50'],
            'issued_on' => ['nullable', 'date', 'before_or_equal:today'],
            'expires_on' => $type->expires()
                ? [
                    'required', 'date',
                    ...($this->filled('issued_on') ? ['after_or_equal:issued_on'] : []),
                    ...($current?->expires_on ? ['after:'.$current->expires_on->toDateString()] : []),
                ]
                : ['prohibited'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_FILE_KB],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'number.required_without' => 'Escribe el número o adjunta el archivo del documento.',
            'expires_on.after' => 'La renovación debe vencer después del documento actual (:date).',
            'file.max' => 'El archivo no puede pesar más de 10 MB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $labels = $this->documentType()->fieldLabels();

        return [
            'number' => mb_strtolower($labels['number']),
            'issued_on' => mb_strtolower($labels['issued_on']),
            'expires_on' => mb_strtolower($labels['expires_on']),
            'file' => 'archivo',
        ];
    }

    public function vehicle(): Vehicle
    {
        return $this->route('vehicle');
    }

    public function documentType(): VehicleDocumentType
    {
        return VehicleDocumentType::from($this->route('type'));
    }

    public function currentDocument(): ?VehicleDocument
    {
        return $this->vehicle()->currentDocuments()->where('type', $this->documentType())->first();
    }

    public function renewing(): bool
    {
        return $this->routeIs('vehicles.documents.renew');
    }

    /**
     * @return array{number: string|null, issued_on: string|null, expires_on: string|null}
     */
    public function documentData(): array
    {
        return [
            'number' => $this->validated('number') ?: null,
            'issued_on' => $this->validated('issued_on'),
            'expires_on' => $this->validated('expires_on'),
        ];
    }
}
