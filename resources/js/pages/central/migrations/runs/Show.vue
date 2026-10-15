<script setup lang="ts">
import StatusBadge from '@/components/central/StatusBadge.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import DataTable from '@/components/table/DataTable.vue';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/composables/useConfirm';
import CentralLayout from '@/layouts/CentralLayout.vue';
import { type BreadcrumbItem, type Paginated, type SelectOption, type TableColumn, type TableQuery } from '@/types';
import { Head, router, usePage, usePoll } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';

interface RunDetail {
    id: number;
    tenant: string;
    source: string;
    started_by: string | null;
    mode_label: string;
    status: string;
    status_label: string;
    is_active: boolean;
    waiting_for_worker: boolean;
    modules: string[];
    started_at: string | null;
    finished_at: string | null;
}

interface StepRow {
    id: number;
    module: string;
    status: string;
    status_label: string;
    read_count: number;
    inserted_count: number;
    updated_count: number;
    quarantined_count: number;
    skipped_count: number;
    total_count: number | null;
    error: string | null;
    started_at: string | null;
    finished_at: string | null;
}

interface IssueRow {
    id: number;
    severity: string;
    severity_label: string;
    module: string | null;
    legacy_table: string | null;
    legacy_id: string | null;
    message: string;
    resolved: boolean;
}

const props = defineProps<{
    run: RunDetail;
    steps: StepRow[];
    issues: Paginated<IssueRow>;
    issuesQuery: TableQuery;
    severities: SelectOption[];
}>();

const issueColumns: TableColumn[] = [
    { key: 'severity', label: 'Tipo', sortable: true },
    { key: 'legacy_id', label: 'Registro legacy', sortable: true },
    { key: 'module', label: 'Paso' },
    { key: 'message', label: 'Detalle', primary: true },
];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Migraciones', href: route('central.migrations.index', undefined, false) },
    { title: `Corrida #${props.run.id}`, href: route('central.migrations.runs.show', { run: props.run.id }, false) },
];

// Progreso en vivo mientras la corrida está pendiente o en curso.
const { stop } = usePoll(1000, { only: ['run', 'steps', 'issues', 'issuesQuery'] }, { autoStart: props.run.is_active });

// Reloj para el tiempo transcurrido del paso en curso.
const now = ref(Date.now());
const clock = props.run.is_active ? window.setInterval(() => (now.value = Date.now()), 1000) : undefined;
onUnmounted(() => window.clearInterval(clock));

watch(
    () => props.run.is_active,
    (isActive) => {
        if (!isActive) {
            stop();
            window.clearInterval(clock);
        }
    },
);

const stepProgress = (step: StepRow): number => {
    if (step.status === 'completed') {
        return 100;
    }

    if (!step.total_count) {
        return 0;
    }

    return Math.min(100, Math.round((step.read_count / step.total_count) * 100));
};

const overallProgress = computed(() =>
    props.steps.length === 0 ? 0 : Math.round(props.steps.reduce((sum, step) => sum + stepProgress(step), 0) / props.steps.length),
);

const currentStep = computed(() => props.steps.find((step) => step.status === 'running'));

const stepDuration = (step: StepRow): string | null => {
    if (!step.started_at) {
        return null;
    }

    const end = step.finished_at ? Date.parse(step.finished_at) : now.value;
    const seconds = Math.max(0, Math.round((end - Date.parse(step.started_at)) / 1000));

    return seconds < 60 ? `${seconds} s` : `${Math.floor(seconds / 60)} min ${seconds % 60} s`;
};

const barClass = (status: string): string =>
    ({
        completed: 'bg-emerald-500',
        failed: 'bg-red-500',
        cancelled: 'bg-muted-foreground',
    })[status] ?? 'bg-primary';

const page = usePage();
const runError = computed(() => (page.props.errors as Record<string, string>).run);

const { confirm } = useConfirm();

const cancelRun = async () => {
    const confirmed = await confirm({
        title: `¿Cancelar la corrida #${props.run.id}?`,
        text: 'Se detiene al terminar el lote en curso. En una corrida real se revierte el paso que estaba corriendo; los pasos ya terminados se conservan.',
        confirmText: 'Cancelar corrida',
        destructive: true,
    });

    if (confirmed) {
        router.post(route('central.migrations.runs.cancel', { run: props.run.id }), {}, { preserveScroll: true });
    }
};

const details = computed(() => [
    { label: 'Empresa', value: props.run.tenant },
    { label: 'Fuente', value: props.run.source },
    { label: 'Modo', value: props.run.mode_label },
    { label: 'Iniciada por', value: props.run.started_by },
    { label: 'Inicio', value: props.run.started_at },
    { label: 'Fin', value: props.run.finished_at },
    { label: 'Módulos', value: props.run.modules.join(', ') },
]);
</script>

<template>
    <CentralLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Corrida #${run.id}`" />

        <div class="flex flex-wrap items-center gap-3">
            <h2 class="text-xl font-semibold tracking-tight">Corrida #{{ run.id }}</h2>
            <StatusBadge :status="run.status" :label="run.status_label" />
            <Button v-if="run.is_active" variant="outline" size="sm" class="w-full sm:ml-auto sm:w-auto" @click="cancelRun">Cancelar corrida</Button>
        </div>
        <InputError :message="runError" />

        <div
            v-if="run.waiting_for_worker"
            class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200"
        >
            La corrida sigue en cola: ningún worker la ha tomado. Verifica que esté corriendo
            <code class="font-mono">php artisan queue:work</code> (en desarrollo, <code class="font-mono">composer run dev</code> lo incluye).
        </div>

        <dl class="grid grid-cols-1 gap-x-8 gap-y-4 rounded-xl border border-sidebar-border/70 p-4 min-[420px]:grid-cols-2 sm:p-6 xl:grid-cols-3">
            <div v-for="item in details" :key="item.label">
                <dt class="text-xs uppercase text-muted-foreground">{{ item.label }}</dt>
                <dd class="mt-1 break-words text-sm">{{ item.value || '—' }}</dd>
            </div>
        </dl>

        <section class="space-y-4">
            <div class="space-y-2 rounded-xl border border-sidebar-border/70 p-4 sm:p-6">
                <div class="flex items-center justify-between text-sm">
                    <span class="font-medium">
                        <template v-if="currentStep">Procesando: {{ currentStep.module }}</template>
                        <template v-else-if="run.status === 'pending'">En cola, esperando a que empiece…</template>
                        <template v-else>Progreso general</template>
                    </span>
                    <span class="tabular-nums text-muted-foreground">{{ overallProgress }} %</span>
                </div>
                <div class="h-3 overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-full rounded-full transition-all duration-700"
                        :class="barClass(run.status)"
                        :style="{ width: `${overallProgress}%` }"
                    />
                </div>
            </div>

            <HeadingSmall title="Pasos" />
            <p v-if="steps.length === 0" class="text-sm text-muted-foreground">Esta corrida todavía no tiene pasos.</p>
            <div class="grid gap-4 lg:grid-cols-2">
                <article
                    v-for="(step, index) in steps"
                    :key="step.id"
                    class="space-y-4 rounded-xl border p-4 sm:p-5"
                    :class="step.status === 'running' ? 'border-primary shadow-sm' : 'border-sidebar-border/70'"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-medium">{{ index + 1 }}. {{ step.module }}</h3>
                        <div class="flex items-center gap-2">
                            <span v-if="stepDuration(step)" class="text-xs tabular-nums text-muted-foreground">{{ stepDuration(step) }}</span>
                            <StatusBadge :status="step.status" :label="step.status_label" />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <div class="h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full transition-all duration-700"
                                :class="[barClass(step.status), { 'animate-pulse': step.status === 'running' }]"
                                :style="{ width: `${stepProgress(step)}%` }"
                            />
                        </div>
                        <p class="text-xs tabular-nums text-muted-foreground">
                            {{ step.read_count }} de {{ step.total_count ?? '…' }} registros leídos · {{ stepProgress(step) }} %
                        </p>
                    </div>

                    <dl class="grid grid-cols-2 gap-2 text-center sm:grid-cols-4">
                        <div class="rounded-lg bg-muted/40 p-2">
                            <dt class="text-xs text-muted-foreground">Insertadas</dt>
                            <dd class="text-lg font-semibold tabular-nums">{{ step.inserted_count }}</dd>
                        </div>
                        <div class="rounded-lg bg-muted/40 p-2">
                            <dt class="text-xs text-muted-foreground">Actualizadas</dt>
                            <dd class="text-lg font-semibold tabular-nums">{{ step.updated_count }}</dd>
                        </div>
                        <div class="rounded-lg bg-muted/40 p-2">
                            <dt class="text-xs text-muted-foreground">Cuarentena</dt>
                            <dd
                                class="text-lg font-semibold tabular-nums"
                                :class="{ 'text-amber-600 dark:text-amber-400': step.quarantined_count > 0 }"
                            >
                                {{ step.quarantined_count }}
                            </dd>
                        </div>
                        <div class="rounded-lg bg-muted/40 p-2">
                            <dt class="text-xs text-muted-foreground">Omitidas</dt>
                            <dd class="text-lg font-semibold tabular-nums">{{ step.skipped_count }}</dd>
                        </div>
                    </dl>

                    <p v-if="step.error" class="text-sm text-red-600 dark:text-red-400">{{ step.error }}</p>
                </article>
            </div>
        </section>

        <section class="flex flex-col gap-4">
            <HeadingSmall
                title="Cuarentena y errores"
                description="Registros que no se importaron y advertencias de la corrida. Descárgalo para revisarlo antes de una corrida real."
            />
            <DataTable
                :rows="issues"
                :columns="issueColumns"
                :query="issuesQuery"
                :url="route('central.migrations.runs.show', { run: run.id })"
                :only="['issues', 'issuesQuery']"
                :export-urls="{
                    xlsx: route('central.migrations.runs.issues.export', { run: run.id, format: 'xlsx' }),
                    pdf: route('central.migrations.runs.issues.export', { run: run.id, format: 'pdf' }),
                }"
                search-placeholder="Buscar en el detalle o por id legacy"
                empty-text="Sin registros en cuarentena ni errores."
            >
                <template #filters="{ query: state, apply }">
                    <select
                        :value="state.severity ?? ''"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm sm:w-auto"
                        aria-label="Tipo"
                        @change="apply({ severity: ($event.target as HTMLSelectElement).value || null })"
                    >
                        <option value="">Todos los tipos</option>
                        <option v-for="severity in severities" :key="severity.value" :value="severity.value">{{ severity.label }}</option>
                    </select>
                </template>

                <template #cell-severity="{ row }">
                    <StatusBadge :status="row.severity" :label="row.severity_label" />
                </template>
                <template #cell-legacy_id="{ row }">
                    <span class="whitespace-nowrap tabular-nums text-muted-foreground">
                        {{ row.legacy_table ? `${row.legacy_table} #${row.legacy_id}` : 'General' }}
                    </span>
                </template>
                <template #cell-message="{ row }">
                    <span class="font-normal" :class="{ 'opacity-60': row.resolved }">{{ row.message }}</span>
                </template>
            </DataTable>
        </section>
    </CentralLayout>
</template>
