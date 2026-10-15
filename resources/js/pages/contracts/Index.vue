<script setup lang="ts">
import ActiveAllyBanner from '@/components/contracts/ActiveAllyBanner.vue';
import Heading from '@/components/Heading.vue';
import ActionButton from '@/components/table/ActionButton.vue';
import DataTable from '@/components/table/DataTable.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Paginated, type TableColumn, type TableQuery } from '@/types';
import { type ContractModule, formatDate } from '@/types/contracts';
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed } from 'vue';

interface ClientRow {
    id: number;
    name: string;
    type_label: string;
    identification: string;
    phone: string;
    contracts_count: number;
    last_ends_on: string | null;
    has_current_contract: boolean;
    url: string;
}

const props = defineProps<{
    module: ContractModule;
    clients: Paginated<ClientRow>;
    query: TableQuery;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: props.module.title, href: props.module.urls.index }]);

const columns: TableColumn[] = [
    { key: 'name', label: 'Cliente', sortable: true, primary: true },
    { key: 'identification', label: 'Identificación' },
    { key: 'phone', label: 'Teléfono' },
    { key: 'contracts_count', label: 'Contratos', sortable: true, align: 'right' },
    { key: 'last_ends_on', label: 'Vence', sortable: true },
    { key: 'status', label: 'Estado' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="module.title" />

        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <Heading
                    :title="module.title"
                    description="Clientes con contratos: cuántos tienen, cuándo vence el último y si tienen uno vigente."
                />
                <Button v-if="module.can_create" as-child class="w-full sm:w-auto">
                    <Link :href="module.urls.create">
                        <Plus />
                        Nuevo contrato
                    </Link>
                </Button>
            </div>

            <ActiveAllyBanner :module="module" />

            <DataTable
                :rows="clients"
                :columns="columns"
                :query="query"
                :url="module.urls.index"
                :only="['clients', 'query']"
                :export-urls="{ xlsx: module.urls.export_xlsx, pdf: module.urls.export_pdf }"
                search-placeholder="Buscar cliente por nombre o identificación"
                empty-text="No hay clientes con contratos que coincidan con la búsqueda."
            >
                <template #filters="{ query: state, apply }">
                    <select
                        :value="state.status ?? ''"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm sm:w-auto"
                        aria-label="Estado"
                        @change="apply({ status: ($event.target as HTMLSelectElement).value || null })"
                    >
                        <option value="">Todos</option>
                        <option value="current">Con contrato vigente</option>
                        <option value="none">Sin contrato vigente</option>
                    </select>
                </template>

                <template #cell-name="{ row }">
                    <Link :href="row.url" class="hover:underline">{{ row.name }}</Link>
                    <span class="block text-xs font-normal text-muted-foreground">{{ row.type_label }}</span>
                </template>
                <template #cell-identification="{ row }">
                    <span class="tabular-nums">{{ row.identification }}</span>
                </template>
                <template #cell-last_ends_on="{ row }">
                    <span class="whitespace-nowrap tabular-nums">{{ formatDate(row.last_ends_on) }}</span>
                </template>
                <template #cell-status="{ row }">
                    <span
                        class="whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium"
                        :class="
                            row.has_current_contract
                                ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200'
                                : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'
                        "
                    >
                        {{ row.has_current_contract ? 'Vigente' : 'Sin vigente' }}
                    </span>
                </template>

                <template #actions="{ row, labeled }">
                    <ActionButton action="view" :labeled="labeled" :label="`Ver contratos de ${row.name}`" :href="row.url" />
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
