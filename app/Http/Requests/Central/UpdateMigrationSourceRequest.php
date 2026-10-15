<?php

namespace App\Http\Requests\Central;

use App\Models\MigrationSource;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos editables de una fuente: nombre, acceso SFTP a los archivos y notas. La conexión a la base
 * no se edita: para cambiarla se registra otra fuente.
 */
class UpdateMigrationSourceRequest extends FormRequest
{
    use LegacyFilesConnectionRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareFilesConnection();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...$this->filesConnectionRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'notes' => 'notas',
            ...$this->filesConnectionAttributes(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->filesConnectionMessages();
    }

    protected function currentFilesConnection(): ?array
    {
        $source = $this->route('source');

        return $source instanceof MigrationSource ? $source->files_connection : null;
    }
}
