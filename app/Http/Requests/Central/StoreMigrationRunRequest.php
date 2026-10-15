<?php

namespace App\Http\Requests\Central;

use App\Enums\MigrationModule;
use App\Enums\MigrationRunMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMigrationRunRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'migration_source_id' => ['required', 'integer', Rule::exists('migration_sources', 'id')],
            'tenant_id' => ['required', 'string', Rule::exists('tenants', 'id')],
            'mode' => ['required', Rule::enum(MigrationRunMode::class)],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['required', 'distinct', Rule::enum(MigrationModule::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'migration_source_id' => 'fuente',
            'tenant_id' => 'empresa',
            'mode' => 'modo',
            'modules' => 'módulos',
            'modules.*' => 'módulo',
        ];
    }

    /**
     * @return list<MigrationModule>
     */
    public function modules(): array
    {
        return array_map(fn (string $module): MigrationModule => MigrationModule::from($module), $this->validated('modules'));
    }
}
