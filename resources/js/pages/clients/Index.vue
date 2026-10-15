<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import ActionButton from '@/components/table/ActionButton.vue';
import DataTable from '@/components/table/DataTable.vue';
import { Button } from '@/components/ui/button';
import { useClientActivation } from '@/composables/useClientActivation';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Paginated, type SelectOption, type TableColumn, type TableQuery } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed } from 'vue';

interface ClientRow {
    id: number;
    type: 'person' | 'company';
    type_label: string;
    identification_type_label: string;
    identification: string;
    check_digit: string | null;
    branch_code: string | null;
    name: string;
    phone: string;
    city: string;
    address: string;
    is_active: boolean;
    can: { update: boolean; toggle_active: boolean };
}

const props = defineProps<{
    clients: Paginated<ClientRow>;
    query: TableQuery;
    types: SelectOption[];
    can: { create: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Clientes', href: '/clients' }];

const showingInactive = computed(() => props.query.status === 'inactive');

const columns: TableColumn[] = [
    { key: 'name', label: 'Nombre / razón social', sortable: true, primary: true },
    { key: 'type', label: 'Tipo' },
    { key: 'identification', label: 'Identificación', sortable: true },
    { key: 'phone', label: 'Teléfono' },
    { key: 'city', label: 'Ciudad', sortable: true },
    { key: 'address', label: 'Dirección' },
];

/** Activos e inactivos son el mismo listado (en el legacy eran dos pantallas). */
const showStatus = (status: 'active' | 'inactive') => {
    router.get(route('clients.index'), status === 'inactive' ? { status } : {}, { preserveState: false });
};

const { deactivate, activate } = useClientActivation();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Clientes" />

        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <Heading title="Clientes" description="Personas y empresas a las que se les presta el servicio." />
                <Button v-if="can.create" as-child class="w-full sm:w-auto">
                    <Link :href="route('clients.create')">
                        <Plus />
                        Crear cliente
                    </Link>
                </Button>
            </div>

            <div
                class="inline-flex w-full rounded-lg border border-sidebar-border/70 p-1 sm:w-auto"
                role="tablist"
                aria-label="Estado de los clientes"
            >
                <button
                    v-for="tab in [
                        { status: 'active', label: 'Activos' },
                        { status: 'inactive', label: 'Inactivos' },
                    ] as const"
                    :key="tab.status"
                    type="button"
                    role="tab"
                    :aria-selected="showingInactive === (tab.status === 'inactive')"
                    class="flex-1 rounded-md px-4 py-1.5 text-sm font-medium transition-colors sm:flex-none"
                    :class="
                        showingInactive === (tab.status === 'inactive')
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted'
                    "
                    @click="showStatus(tab.status)"
                >
                    {{ tab.label }}
                </button>
            </div>

            <DataTable
                :rows="clients"
                :columns="columns"
                :query="query"
                :url="route('clients.index')"
                :only="['clients', 'query']"
                :export-urls="{ xlsx: route('clients.export', { format: 'xlsx' }), pdf: route('clients.export', { format: 'pdf' }) }"
                search-placeholder="Buscar por identificación, nombre, teléfono o ciudad"
                :empty-text="showingInactive ? 'No hay clientes inactivos.' : 'No hay clientes que coincidan con la búsqueda.'"
            >
                <template #filters="{ query: state, apply }">
                    <select
                        :value="state.type ?? ''"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm sm:w-auto"
                        aria-label="Tipo de cliente"
                        @change="apply({ type: ($event.target as HTMLSelectElement).value || null })"
                    >
                        <option value="">Todos los tipos</option>
                        <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
                    </select>
                </template>

                <template #cell-name="{ row }">
                    <Link :href="route('clients.show', { client: row.id })" class="hover:underline">{{ row.name || '—' }}</Link>
                </template>
                <template #cell-type="{ row }">
                    <span
                        class="whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium"
                        :class="
                            row.type === 'company'
                                ? 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200'
                                : 'bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-200'
                        "
                    >
                        {{ row.type_label }}
                    </span>
                </template>
                <template #cell-identification="{ row }">
                    <span class="whitespace-nowrap tabular-nums">
                        {{ row.identification }}<template v-if="row.check_digit">-{{ row.check_digit }}</template>
                    </span>
                    <span class="block text-xs text-muted-foreground">
                        {{ row.identification_type_label }}<template v-if="row.branch_code"> · Suc. {{ row.branch_code }}</template>
                    </span>
                </template>
                <template #cell-address="{ row }">
                    <span class="text-muted-foreground">{{ row.address }}</span>
                </template>

                <template #actions="{ row, labeled }">
                    <ActionButton action="view" :labeled="labeled" :label="`Ver ${row.name}`" :href="route('clients.show', { client: row.id })" />
                    <ActionButton
                        v-if="row.can.update"
                        action="edit"
                        :labeled="labeled"
                        :label="`Editar ${row.name}`"
                        :href="route('clients.edit', { client: row.id })"
                    />
                    <template v-if="row.can.toggle_active">
                        <ActionButton
                            v-if="row.is_active"
                            action="deactivate"
                            :labeled="labeled"
                            :label="`Inhabilitar ${row.name}`"
                            @click="deactivate(row)"
                        />
                        <ActionButton v-else action="activate" :labeled="labeled" :label="`Habilitar ${row.name}`" @click="activate(row)" />
                    </template>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
