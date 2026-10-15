<script setup lang="ts">
import { CircleAlert, Clock } from 'lucide-vue-next';
import { computed, onUnmounted, ref } from 'vue';

export interface QueueStatus {
    reason: string;
    reason_code: 'failed' | 'reserved' | 'missing' | 'no_worker' | 'busy' | 'should_start';
    waiting_since: string | null;
    connection: string;
    queue: string;
    worker_last_seen: string | null;
    worker_active: boolean;
    jobs_ahead: number | null;
    reserved_at: string | null;
    failed_error: string | null;
}

/**
 * Por qué una corrida sigue en cola (App\Actions\Migrations\MigrationRunQueueStatus): el motivo,
 * los datos de la cola y cómo arrancar el worker. Se actualiza con el refresco de la página.
 */
const props = defineProps<{ status: QueueStatus }>();

const now = ref(Date.now());
const clock = window.setInterval(() => (now.value = Date.now()), 1000);
onUnmounted(() => window.clearInterval(clock));

const relative = (iso: string | null): string => {
    if (!iso) {
        return 'nunca';
    }

    const seconds = Math.round((new Date(iso).getTime() - now.value) / 1000);
    const format = new Intl.RelativeTimeFormat('es', { numeric: 'auto' });
    const abs = Math.abs(seconds);

    if (abs < 60) {
        return format.format(seconds, 'second');
    }

    if (abs < 3600) {
        return format.format(Math.round(seconds / 60), 'minute');
    }

    return abs < 86400 ? format.format(Math.round(seconds / 3600), 'hour') : format.format(Math.round(seconds / 86400), 'day');
};

const time = (iso: string | null) =>
    iso ? new Date(iso).toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '';

const isError = computed(() => ['failed', 'missing'].includes(props.status.reason_code));
const showsHowToStart = computed(() => ['no_worker', 'reserved', 'should_start', 'busy'].includes(props.status.reason_code));

const details = computed(() => [
    {
        label: 'En cola desde',
        value: props.status.waiting_since ? `${time(props.status.waiting_since)} (${relative(props.status.waiting_since)})` : '—',
    },
    {
        label: 'Worker',
        value: props.status.worker_active
            ? `Activo · último latido ${relative(props.status.worker_last_seen)}`
            : `Ninguno activo · último visto ${relative(props.status.worker_last_seen)}`,
    },
    { label: 'Conexión / cola', value: `${props.status.connection} / ${props.status.queue}` },
    { label: 'Trabajos por delante', value: props.status.jobs_ahead === null ? '—' : String(props.status.jobs_ahead) },
    ...(props.status.reserved_at
        ? [{ label: 'Tomada por un worker', value: `${time(props.status.reserved_at)} (${relative(props.status.reserved_at)})` }]
        : []),
]);
</script>

<template>
    <section
        class="flex flex-col gap-4 rounded-xl border p-4 text-sm"
        :class="
            isError
                ? 'border-red-300 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-200'
                : 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200'
        "
        role="status"
    >
        <div class="flex items-start gap-2">
            <component :is="isError ? CircleAlert : Clock" class="mt-0.5 size-4 shrink-0" />
            <div class="space-y-1">
                <p class="font-medium">La corrida sigue en cola</p>
                <p>{{ status.reason }}</p>
            </div>
        </div>

        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 min-[420px]:grid-cols-2 lg:grid-cols-3">
            <div v-for="detail in details" :key="detail.label" class="min-w-0">
                <dt class="text-xs uppercase opacity-75">{{ detail.label }}</dt>
                <dd class="mt-0.5 break-words">{{ detail.value }}</dd>
            </div>
        </dl>

        <p v-if="status.failed_error" class="break-words rounded-md bg-background/60 p-2 font-mono text-xs">{{ status.failed_error }}</p>

        <details v-if="showsHowToStart" class="rounded-lg bg-background/60 p-3" :open="status.reason_code === 'no_worker'">
            <summary class="cursor-pointer font-medium">Cómo arrancar el worker</summary>
            <ul class="mt-2 list-disc space-y-2 pl-5">
                <li>
                    <span class="font-medium">En tu computador (Herd):</span> abre una terminal en la carpeta del proyecto y deja corriendo
                    <code class="font-mono">php artisan queue:work</code>. También sirve <code class="font-mono">composer run dev</code>, que además
                    levanta Vite. Si cierras la terminal, el worker se detiene.
                </li>
                <li>
                    <span class="font-medium">En el servidor:</span> el worker lo mantiene Supervisor. Revisa que esté arriba con
                    <code class="font-mono">sudo supervisorctl status</code> y reinícialo con
                    <code class="font-mono">sudo supervisorctl restart &lt;programa&gt;</code>.
                </li>
                <li>
                    <span class="font-medium">Después de cambiar el código:</span> <code class="font-mono">php artisan queue:restart</code> para que
                    los workers tomen la versión nueva.
                </li>
            </ul>
        </details>
    </section>
</template>
