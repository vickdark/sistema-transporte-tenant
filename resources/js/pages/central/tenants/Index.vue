<script setup lang="ts">
import StatusBadge from '@/components/central/StatusBadge.vue';
import Heading from '@/components/Heading.vue';
import ActionButton from '@/components/table/ActionButton.vue';
import DataTable from '@/components/table/DataTable.vue';
import { Button } from '@/components/ui/button';
import CentralLayout from '@/layouts/CentralLayout.vue';
import { type BreadcrumbItem, type Paginated, type SelectOption, type TableColumn, type TableQuery } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';

interface TenantRow {
    id: string;
    name: string;
    nit: string | null;
    status: string;
    status_label: string;
    subdomain: string | null;
    created_at: string | null;
}

defineProps<{
    tenants: Paginated<TenantRow>;
    query: TableQuery;
    statuses: SelectOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Empresas', href: route('central.tenants.index', undefined, false) }];

const columns: TableColumn[] = [
    { key: 'name', label: 'Empresa', sortable: true, primary: true },
    { key: 'nit', label: 'NIT', sortable: true },
    { key: 'subdomain', label: 'Subdominio' },
    { key: 'status', label: 'Estado', sortable: true },
    { key: 'created_at', label: 'Creada', sortable: true },
];
</script>

<template>
    <CentralLayout :breadcrumbs="breadcrumbs">
        <Head title="Empresas" />

        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <Heading title="Empresas" description="Inquilinos de la plataforma: cada empresa de transporte con su subdominio y usuarios." />
            <Button as-child class="w-full sm:w-auto">
                <Link :href="route('central.tenants.create')">
                    <Plus />
                    Nueva empresa
                </Link>
            </Button>
        </div>

        <DataTable
            :rows="tenants"
            :columns="columns"
            :query="query"
            :url="route('central.tenants.index')"
            :only="['tenants', 'query']"
            :export-urls="{ xlsx: route('central.tenants.export', { format: 'xlsx' }), pdf: route('central.tenants.export', { format: 'pdf' }) }"
            search-placeholder="Buscar por nombre o NIT"
            empty-text="No hay empresas que coincidan con la búsqueda."
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

            <template #cell-name="{ row }">
                <Link :href="route('central.tenants.show', { tenant: row.id })" class="hover:underline">{{ row.name }}</Link>
            </template>
            <template #cell-nit="{ row }">
                <span class="tabular-nums">{{ row.nit ?? '—' }}</span>
            </template>
            <template #cell-subdomain="{ row }">
                <span class="text-muted-foreground">{{ row.subdomain ?? '—' }}</span>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge :status="row.status" :label="row.status_label" />
            </template>

            <template #actions="{ row, labeled }">
                <ActionButton action="view" :labeled="labeled" :label="`Ver ${row.name}`" :href="route('central.tenants.show', { tenant: row.id })" />
                <ActionButton
                    action="edit"
                    :labeled="labeled"
                    :label="`Editar ${row.name}`"
                    :href="route('central.tenants.edit', { tenant: row.id })"
                />
            </template>
        </DataTable>
    </CentralLayout>
</template>
