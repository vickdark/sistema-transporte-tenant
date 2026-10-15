<?php

namespace App\Http\Requests\Central;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Por ahora solo se registran conexiones MySQL de solo lectura; la carga de dump depende de
 * la decisión #4 del plan.
 */
class StoreMigrationSourceRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'host' => 'servidor',
            'port' => 'puerto',
            'database' => 'base de datos',
            'username' => 'usuario',
            'password' => 'contraseña',
            'notes' => 'notas',
        ];
    }

    /**
     * @return array{host: string, port: int, database: string, username: string, password: string}
     */
    public function credentials(): array
    {
        return [
            'host' => $this->validated('host'),
            'port' => (int) $this->validated('port'),
            'database' => $this->validated('database'),
            'username' => $this->validated('username'),
            'password' => (string) $this->validated('password'),
        ];
    }
}
