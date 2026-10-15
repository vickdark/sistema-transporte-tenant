<?php

namespace App\Http\Requests\Central;

use App\Actions\Migrations\LegacyFilesConnection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use RuntimeException;

/**
 * Acceso SFTP opcional a los archivos del legacy en una fuente: servidor, puerto, usuario,
 * contraseña o llave privada y la carpeta que contiene `uploads/`. Sin servidor no hay acceso a
 * archivos. Al guardar se prueba la conexión y que la carpeta tenga `uploads/`.
 *
 * Al editar, la contraseña y la llave vacías conservan las guardadas.
 *
 * @mixin FormRequest
 */
trait LegacyFilesConnectionRules
{
    /**
     * Acceso guardado (al editar) cuyos secretos se conservan si no se escriben otros.
     *
     * @return array{host: string, port: int, username: string, password: string|null, private_key: string|null, root: string}|null
     */
    abstract protected function currentFilesConnection(): ?array;

    protected function prepareFilesConnection(): void
    {
        $this->merge([
            'files_host' => is_string($this->input('files_host')) ? trim($this->input('files_host')) : $this->input('files_host'),
            'files_root' => LegacyFilesConnection::normalizeRoot($this->input('files_root')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function filesConnectionRules(): array
    {
        $hasSecret = $this->currentFilesConnection() !== null;

        return [
            'files_host' => ['nullable', 'string', 'max:255'],
            'files_port' => ['exclude_without:files_host', 'required', 'integer', 'between:1,65535'],
            'files_username' => ['exclude_without:files_host', 'required', 'string', 'max:255'],
            'files_password' => ['exclude_without:files_host', 'nullable', 'string', 'max:255', ...($hasSecret ? [] : ['required_without:files_private_key'])],
            'files_private_key' => ['exclude_without:files_host', 'nullable', 'string', 'max:20000'],
            'files_root' => ['exclude_without:files_host', 'required', 'string', 'max:500', 'starts_with:/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function filesConnectionAttributes(): array
    {
        return [
            'files_host' => 'servidor SFTP',
            'files_port' => 'puerto SFTP',
            'files_username' => 'usuario SFTP',
            'files_password' => 'contraseña SFTP',
            'files_private_key' => 'llave privada',
            'files_root' => 'carpeta del legacy',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function filesConnectionMessages(): array
    {
        return [
            'files_password.required_without' => 'Escribe la contraseña SFTP o pega la llave privada.',
            'files_root.starts_with' => 'Escribe la ruta completa en el servidor, desde la raíz (p. ej. /var/www/mi-legacy).',
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $connection = $this->filesConnection();

            if ($validator->errors()->isNotEmpty() || $connection === null) {
                return;
            }

            $files = app(LegacyFilesConnection::class);

            try {
                $hasUploads = $files->hasUploads($files->open($connection), $connection);
            } catch (RuntimeException $exception) {
                $validator->errors()->add('files_host', $exception->getMessage());

                return;
            }

            if (! $hasUploads) {
                $validator->errors()->add('files_root', "En el servidor no existe {$connection['root']}/uploads: escribe la carpeta del legacy que contiene uploads/.");
            }
        }];
    }

    /**
     * Acceso a guardar, o null si no se configuró servidor.
     *
     * @return array{host: string, port: int, username: string, password: string|null, private_key: string|null, root: string}|null
     */
    public function filesConnection(): ?array
    {
        if (blank($this->input('files_host'))) {
            return null;
        }

        $current = $this->currentFilesConnection();
        $password = (string) $this->input('files_password');
        $privateKey = trim((string) $this->input('files_private_key'));
        $keepSecret = $password === '' && $privateKey === '';

        return [
            'host' => (string) $this->input('files_host'),
            'port' => (int) $this->input('files_port'),
            'username' => trim((string) $this->input('files_username')),
            'password' => $keepSecret ? ($current['password'] ?? null) : ($password ?: null),
            'private_key' => $keepSecret ? ($current['private_key'] ?? null) : ($privateKey ?: null),
            'root' => (string) $this->input('files_root'),
        ];
    }
}
