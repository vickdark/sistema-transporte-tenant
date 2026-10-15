<script setup lang="ts">
import StatusBadge from '@/components/central/StatusBadge.vue';
import Heading from '@/components/Heading.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import ActionButton from '@/components/table/ActionButton.vue';
import DataTable from '@/components/table/DataTable.vue';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/composables/useConfirm';
import CentralLayout from '@/layouts/CentralLayout.vue';
import { type BreadcrumbItem, type Paginated, type SelectOption, type TableColumn, type TableQuery } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed } from 'vue';

interface SourceRow {
    id: number;
    name: string;
    type_label: string;
    host: string | null;
    database: string | null;
    runs_count: number;
    created_at: string | null;
}

interface RunRow {
    id: number;
    tenant: string;
    source: string;
    mode_label: string;
    status: string;
    status_label: string;
    created_at: string | null;
}

defineProps<{
    sources: SourceRow[];
    runs: Paginated<RunRow>;
    query: TableQuery;
    statuses: SelectOption[];
}>();

const page = usePage();
const sourceError = computed(() => (page.props.errors as Record<string, string>).source);

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Migraciones', href: route('central.migrations.index', undefined, false) }];

const runColumns: TableColumn[] = [
    { key: 'id', label: '#', sortable: true, primary: true },
    { key: 'tenant', label: 'Empresa' },
    { key: 'source', label: 'Fuente' },
    { key: 'mode_label', label: 'Modo' },
    { key: 'status', label: 'Estado', sortable: true },
    { key: 'created_at', label: 'Creada', sortable: true },
];

const { confirm } = useConfirm();

const removeSource = async (source: SourceRow) => {
    const confirmed = await confirm({
        title: `¿Eliminar la fuente ${source.name}?`,
        text: 'Se borran la conexión y sus credenciales guardadas.',
        confirmText: 'Eliminar',
        destructive: true,
    });

    if (confirmed) {
        router.delete(route('central.migrations.sources.destroy', { source: source.id }), { preserveScroll: true });
    }
};
</script>

<template>
    <CentralLayout :breadcrumbs="breadcrumbs">
        <Head title="Migraciones" />

        <Heading
            title="Migración de datos"
            description="Traslado de datos desde un sistema legacy hacia una empresa. Hoy se migran roles y usuarios; los demás importadores llegan con cada módulo."
        />

        <section class="flex flex-col gap-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <HeadingSmall
                    title="Fuentes legacy"
                    description="Conexiones de solo lectura a una copia de la base de datos legacy. Las credenciales se guardan cifradas."
                />
                <Button variant="outline" as-child class="w-full sm:w-auto">
                    <Link :href="route('central.migrations.sources.create')">
                        <Plus />
                        Registrar fuente
                    </Link>
                </Button>
            </div>

            <div class="overflow-hidden rounded-xl border border-sidebar-border/70">
                <table class="hidden w-full text-left text-sm lg:table">
                    <thead class="bg-muted/50 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Nombre</th>
                            <th class="px-4 py-3 font-medium">Tipo</th>
                            <th class="px-4 py-3 font-medium">Servidor / base</th>
                            <th class="px-4 py-3 text-right font-medium">Corridas</th>
                            <th class="px-4 py-3"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr v-for="source in sources" :key="source.id">
                            <td class="px-4 py-3 font-medium">{{ source.name }}</td>
                            <td class="px-4 py-3">{{ source.type_label }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ source.host }} / {{ source.database }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ source.runs_count }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end">
                                    <ActionButton
                                        v-if="source.runs_count === 0"
                                        action="delete"
                                        :label="`Eliminar ${source.name}`"
                                        @click="removeSource(source)"
                                    />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <ul class="divide-y divide-sidebar-border/70 lg:hidden">
                    <li v-for="source in sources" :key="source.id" class="flex flex-col gap-3 p-4">
                        <p class="font-medium">{{ source.name }}</p>
                        <dl class="grid grid-cols-1 gap-x-4 gap-y-2 text-sm min-[420px]:grid-cols-2">
                            <div class="min-w-0">
                                <dt class="text-xs text-muted-foreground">Servidor / base</dt>
                                <dd class="break-words">{{ source.host }} / {{ source.database }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">Corridas</dt>
                                <dd class="tabular-nums">{{ source.runs_count }}</dd>
                            </div>
                        </dl>
                        <div v-if="source.runs_count === 0">
                            <ActionButton action="delete" labeled :label="`Eliminar ${source.name}`" @click="removeSource(source)" />
                        </div>
                    </li>
                </ul>

                <p v-if="sources.length === 0" class="px-4 py-10 text-center text-sm text-muted-foreground">Todavía no hay fuentes registradas.</p>
            </div>
            <InputError :message="sourceError" />
        </section>

        <section class="flex flex-col gap-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <HeadingSmall
                    title="Corridas"
                    description="Cada ejecución (simulación o real) queda registrada con sus pasos, contadores y cuarentena."
                />
                <Button v-if="sources.length > 0" as-child class="w-full sm:w-auto">
                    <Link :href="route('central.migrations.runs.create')">
                        <Plus />
                        Nueva corrida
                    </Link>
                </Button>
            </div>

            <DataTable
                :rows="runs"
                :columns="runColumns"
                :query="query"
                :url="route('central.migrations.index')"
                :only="['runs', 'query']"
                :export-urls="{
                    xlsx: route('central.migrations.runs.export', { format: 'xlsx' }),
                    pdf: route('central.migrations.runs.export', { format: 'pdf' }),
                }"
                search-placeholder="Buscar por empresa o fuente"
                empty-text="Aún no hay corridas. Registra una fuente y empieza con una simulación."
            >
                <template #filters="{ query: state, apply }">
                    <select
                        :value="state.status ?? ''"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm sm:w-auto"
                        aria-label="Estado"
                        @change="apply({ status: ($event.target as HTMLSelectElement).value || null })"
                    >
                        <option value="">Todos los estados</option>
                        <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                    </select>
                </template>

                <template #cell-id="{ row }">
                    <Link :href="route('central.migrations.runs.show', { run: row.id })" class="tabular-nums hover:underline"
                        >Corrida #{{ row.id }}</Link
                    >
                </template>
                <template #cell-status="{ row }">
                    <StatusBadge :status="row.status" :label="row.status_label" />
                </template>
                <template #cell-created_at="{ row }">
                    <span class="text-muted-foreground">{{ row.created_at }}</span>
                </template>

                <template #actions="{ row, labeled }">
                    <ActionButton
                        action="view"
                        :labeled="labeled"
                        :label="`Ver corrida #${row.id}`"
                        :href="route('central.migrations.runs.show', { run: row.id })"
                    />
                </template>
            </DataTable>
        </section>
    </CentralLayout>
</template>
