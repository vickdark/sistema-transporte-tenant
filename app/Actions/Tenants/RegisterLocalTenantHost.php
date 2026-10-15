<?php

namespace App\Actions\Tenants;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Desarrollo local con Laravel Herd: registra {subdominio}.test como sitio de este proyecto
 * (`herd link`) y quita el subdominio anterior (`herd unlink`). En producción no hace nada:
 * allí un registro DNS comodín y el virtual host comodín cubren todas las empresas.
 */
class RegisterLocalTenantHost
{
    public function isEnabled(): bool
    {
        return (bool) config('tenancy.herd.auto_link');
    }

    /**
     * @return bool false si Herd está activado pero el comando falló (el sitio hay que registrarlo a mano).
     */
    public function handle(string $subdomain, ?string $previousSubdomain = null): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        if ($previousSubdomain !== null && $previousSubdomain !== $subdomain) {
            $this->herd('unlink', $previousSubdomain);
        }

        return $this->herd('link', $subdomain);
    }

    /**
     * Quita el sitio local de una empresa eliminada.
     */
    public function remove(string $subdomain): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        return $this->herd('unlink', $subdomain);
    }

    private function herd(string $command, string $site): bool
    {
        $result = Process::path(base_path())
            ->timeout(60)
            ->run([config('tenancy.herd.binary'), $command, $site]);

        if ($result->failed()) {
            Log::warning("No se pudo ejecutar `herd {$command} {$site}`.", [
                'output' => $result->output(),
                'error' => $result->errorOutput(),
            ]);
        }

        return $result->successful();
    }
}
