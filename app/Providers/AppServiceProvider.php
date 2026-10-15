<?php

namespace App\Providers;

use App\Actions\Migrations\MigrationRunQueueStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->recordWorkerHeartbeat();
    }

    /**
     * Cada worker de la cola deja un latido (como mucho cada 10 s) en cada vuelta, también cuando
     * no hay trabajos: así el panel sabe si hay alguno vivo cuando una corrida no arranca.
     */
    private function recordWorkerHeartbeat(): void
    {
        $lastBeat = 0.0;

        Queue::looping(function () use (&$lastBeat): void {
            if (microtime(true) - $lastBeat >= 10) {
                $lastBeat = microtime(true);
                Cache::put(MigrationRunQueueStatus::HEARTBEAT_KEY, now()->toIso8601String(), now()->addDay());
            }
        });
    }
}
