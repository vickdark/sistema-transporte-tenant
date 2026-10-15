<?php

namespace App\Http\Requests\Central;

use App\Models\MigrationSource;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Prueba el acceso SFTP a los archivos del legacy sin guardar nada (botón "Probar conexión" del
 * formulario de la fuente). Al editar una fuente (`source_id`), la contraseña y la llave vacías
 * usan las guardadas.
 */
class TestLegacyFilesConnectionRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->filesConnectionRules(),
            'files_host' => ['required', 'string', 'max:255'],
            'source_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->filesConnectionAttributes();
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
        return $this->filled('source_id') ? MigrationSource::query()->find($this->integer('source_id'))?->files_connection : null;
    }
}
