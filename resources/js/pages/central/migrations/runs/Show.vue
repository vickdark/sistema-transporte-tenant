<script setup lang="ts">
import StatusBadge from '@/components/central/StatusBadge.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import CentralLayout from '@/layouts/CentralLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

interface RunDetail {
    id: number;
    tenant: string;
    source: string;
    started_by: string | null;
    mode_label: string;
    status: string;
    status_label: string;
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
    error: string | null;
}

interface IssueRow {
    id: number;
    severity: string;
    severity_label: string;
    legacy_table: string | null;
    legacy_id: string | null;
    message: string;
    resolved: boolean;
}

const props = defineProps<{
    run: RunDetail;
    steps: StepRow[];
    issues: IssueRow[];
    issuesCount: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Migraciones', href: route('central.migrations.index', undefined, false) },
    { title: `Corrida #${props.run.id}`, href: route('central.migrations.runs.show', { run: props.run.id }, false) },
];

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

        <div class="flex items-center gap-3">
            <h2 class="text-xl font-semibold tracking-tight">Corrida #{{ run.id }}</h2>
            <StatusBadge :status="run.status" :label="run.status_label" />
        </div>

        <dl class="grid gap-x-8 gap-y-4 rounded-xl border border-sidebar-border/70 p-6 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="item in details" :key="item.label">
                <dt class="text-xs uppercase text-muted-foreground">{{ item.label }}</dt>
                <dd class="mt-1 text-sm">{{ item.value || '—' }}</dd>
            </div>
        </dl>

        <section class="space-y-4">
            <HeadingSmall title="Pasos" />
            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/50 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Módulo</th>
                            <th class="px-4 py-3 font-medium">Estado</th>
                            <th class="px-4 py-3 text-right font-medium">Leídas</th>
                            <th class="px-4 py-3 text-right font-medium">Insertadas</th>
                            <th class="px-4 py-3 text-right font-medium">Actualizadas</th>
                            <th class="px-4 py-3 text-right font-medium">Cuarentena</th>
                            <th class="px-4 py-3 text-right font-medium">Omitidas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <template v-for="step in steps" :key="step.id">
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ step.module }}</td>
                                <td class="px-4 py-3"><StatusBadge :status="step.status" :label="step.status_label" /></td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ step.read_count }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ step.inserted_count }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ step.updated_count }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ step.quarantined_count }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ step.skipped_count }}</td>
                            </tr>
                            <tr v-if="step.error">
                                <td colspan="7" class="px-4 pb-3 text-sm text-red-600 dark:text-red-400">{{ step.error }}</td>
                            </tr>
                        </template>
                        <tr v-if="steps.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">Esta corrida todavía no tiene pasos.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="space-y-4">
            <HeadingSmall
                title="Cuarentena y errores"
                :description="issuesCount > issues.length ? `Se muestran los ${issues.length} más recientes de ${issuesCount}.` : undefined"
            />
            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/50 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Tipo</th>
                            <th class="px-4 py-3 font-medium">Registro legacy</th>
                            <th class="px-4 py-3 font-medium">Detalle</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr v-for="issue in issues" :key="issue.id" :class="{ 'opacity-60': issue.resolved }">
                            <td class="px-4 py-3"><StatusBadge :status="issue.severity" :label="issue.severity_label" /></td>
                            <td class="px-4 py-3 tabular-nums text-muted-foreground">{{ issue.legacy_table }} #{{ issue.legacy_id }}</td>
                            <td class="px-4 py-3">{{ issue.message }}</td>
                        </tr>
                        <tr v-if="issues.length === 0">
                            <td colspan="3" class="px-4 py-10 text-center text-muted-foreground">Sin registros en cuarentena ni errores.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </CentralLayout>
</template>
