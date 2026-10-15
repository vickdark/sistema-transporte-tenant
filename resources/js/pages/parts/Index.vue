<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import ActionButton from '@/components/table/ActionButton.vue';
import DataTable from '@/components/table/DataTable.vue';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Paginated, type TableColumn, type TableQuery } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';

interface PartRow {
    id: number;
    name: string;
    vehicles_count: number;
    average_interval_km: number | null;
    min_interval_km: number | null;
    max_interval_km: number | null;
}

defineProps<{
    parts: Paginated<PartRow>;
    query: TableQuery;
    can: { manage: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Vehículos', href: route('vehicles.index') },
    { title: 'Partes', href: route('parts.index') },
];

const columns: TableColumn[] = [
    { key: 'name', label: 'Parte', sortable: true, primary: true },
    { key: 'vehicles_count', label: 'Vehículos activos', sortable: true, align: 'right' },
    { key: 'average', label: 'Kilometraje promedio', align: 'right' },
    { key: 'range', label: 'Rango de kilometraje', align: 'right' },
];

const km = (value: number | null) => (value === null ? '—' : `${new Intl.NumberFormat('es-CO').format(value)} km`);

const { confirm } = useConfirm();

const remove = async (part: PartRow) => {
    const confirmed = await confirm({
        title: `¿Eliminar la parte "${part.name}"?`,
        text:
            part.vehicles_count > 0
                ? `Se quitará del catálogo y de los vehículos que la tienen (${part.vehicles_count} activos). No se puede deshacer.`
                : 'Se quitará del catálogo. No se puede deshacer.',
        confirmText: 'Eliminar',
        destructive: true,
    });

    if (confirmed) {
        router.delete(route('parts.destroy', { part: part.id }), { preserveScroll: true });
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Partes de vehículos" />

        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <Heading
                    title="Partes de vehículos"
                    description="Catálogo de partes y cada cuántos kilómetros se les hace mantenimiento en cada vehículo."
                />
                <Button v-if="can.manage" as-child class="w-full sm:w-auto">
                    <Link :href="route('parts.create')">
                        <Plus />
                        Crear parte
                    </Link>
                </Button>
            </div>

            <DataTable
                :rows="parts"
                :columns="columns"
                :query="query"
                :url="route('parts.index')"
                :only="['parts', 'query']"
                :export-urls="{ xlsx: route('parts.export', { format: 'xlsx' }), pdf: route('parts.export', { format: 'pdf' }) }"
                search-placeholder="Buscar parte"
                empty-text="No hay partes que coincidan con la búsqueda."
            >
                <template #cell-name="{ row }">
                    <Link :href="route('parts.show', { part: row.id })" class="font-medium hover:underline">{{ row.name }}</Link>
                </template>
                <template #cell-vehicles_count="{ row }">
                    <span class="tabular-nums">{{ row.vehicles_count }}</span>
                </template>
                <template #cell-average="{ row }">
                    <span class="whitespace-nowrap tabular-nums">{{ km(row.average_interval_km) }}</span>
                </template>
                <template #cell-range="{ row }">
                    <span class="whitespace-nowrap tabular-nums text-muted-foreground">
                        <template v-if="row.min_interval_km === null">—</template>
                        <template v-else-if="row.min_interval_km === row.max_interval_km">{{ km(row.min_interval_km) }}</template>
                        <template v-else>{{ km(row.min_interval_km) }} – {{ km(row.max_interval_km) }}</template>
                    </span>
                </template>

                <template #actions="{ row, labeled }">
                    <ActionButton action="view" :labeled="labeled" :label="`Ver ${row.name}`" :href="route('parts.show', { part: row.id })" />
                    <ActionButton v-if="can.manage" action="delete" :labeled="labeled" :label="`Eliminar ${row.name}`" @click="remove(row)" />
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
