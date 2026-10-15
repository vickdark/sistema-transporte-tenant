<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import RouteStatusBadge from '@/components/RouteStatusBadge.vue';
import ActionButton from '@/components/table/ActionButton.vue';
import DataTable from '@/components/table/DataTable.vue';
import { Button } from '@/components/ui/button';
import { useRouteActivation } from '@/composables/useRouteActivation';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Paginated, type SelectOption, type TableColumn, type TableQuery } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed } from 'vue';

interface RouteRow {
    id: number;
    name: string;
    description: string | null;
    type_label: string;
    status: string;
    status_label: string;
    created_at: string | null;
    can: { update: boolean; toggle_active: boolean };
}

const props = defineProps<{
    routes: Paginated<RouteRow>;
    query: TableQuery;
    types: SelectOption[];
    can: { create: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Rutas', href: '/routes' }];

const showingInactive = computed(() => props.query.status === 'inactive');

const columns: TableColumn[] = [
    { key: 'name', label: 'Ruta', sortable: true, primary: true },
    { key: 'description', label: 'Descripción' },
    { key: 'type_label', label: 'Tipo' },
    { key: 'status', label: 'Estado', sortable: true },
    { key: 'created_at', label: 'Creada', sortable: true },
];

/** Activas (incluidas las que están en mantenimiento) e inactivas, como en Clientes. */
const showStatus = (status: 'active' | 'inactive') => {
    router.get(route('routes.index'), status === 'inactive' ? { status } : {}, { preserveState: false });
};

const { deactivate, activate } = useRouteActivation();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Rutas" />

        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <Heading title="Rutas" description="Trayectos que se asignan a las órdenes de servicio." />
                <Button v-if="can.create" as-child class="w-full sm:w-auto">
                    <Link :href="route('routes.create')">
                        <Plus />
                        Crear ruta
                    </Link>
                </Button>
            </div>

            <div class="inline-flex w-full rounded-lg border border-sidebar-border/70 p-1 sm:w-auto" role="tablist" aria-label="Estado de las rutas">
                <button
                    v-for="tab in [
                        { status: 'active', label: 'Activas' },
                        { status: 'inactive', label: 'Inactivas' },
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
                :rows="routes"
                :columns="columns"
                :query="query"
                :url="route('routes.index')"
                :only="['routes', 'query']"
                :export-urls="{ xlsx: route('routes.export', { format: 'xlsx' }), pdf: route('routes.export', { format: 'pdf' }) }"
                search-placeholder="Buscar por inicio, fin o descripción"
                :empty-text="showingInactive ? 'No hay rutas inactivas.' : 'No hay rutas que coincidan con la búsqueda.'"
            >
                <template #filters="{ query: state, apply }">
                    <select
                        :value="state.type ?? ''"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm sm:w-auto"
                        aria-label="Tipo de ruta"
                        @change="apply({ type: ($event.target as HTMLSelectElement).value || null })"
                    >
                        <option value="">Todos los tipos</option>
                        <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
                    </select>
                </template>

                <template #cell-name="{ row }">
                    <Link :href="route('routes.show', { route: row.id })" class="hover:underline">{{ row.name }}</Link>
                </template>
                <template #cell-description="{ row }">
                    <span class="line-clamp-2 text-muted-foreground">{{ row.description ?? '—' }}</span>
                </template>
                <template #cell-status="{ row }">
                    <RouteStatusBadge :status="row.status" :label="row.status_label" />
                </template>
                <template #cell-created_at="{ row }">
                    <span class="whitespace-nowrap text-muted-foreground">{{ row.created_at }}</span>
                </template>

                <template #actions="{ row, labeled }">
                    <ActionButton action="view" :labeled="labeled" :label="`Ver ${row.name}`" :href="route('routes.show', { route: row.id })" />
                    <ActionButton
                        v-if="row.can.update"
                        action="edit"
                        :labeled="labeled"
                        :label="`Editar ${row.name}`"
                        :href="route('routes.edit', { route: row.id })"
                    />
                    <template v-if="row.can.toggle_active">
                        <ActionButton
                            v-if="row.status !== 'inactive'"
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
