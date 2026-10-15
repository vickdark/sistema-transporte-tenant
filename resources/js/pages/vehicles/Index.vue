<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import ActionButton from '@/components/table/ActionButton.vue';
import DataTable from '@/components/table/DataTable.vue';
import { Button } from '@/components/ui/button';
import VehicleOwnerBadge from '@/components/vehicles/VehicleOwnerBadge.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useVehicleActivation } from '@/composables/useVehicleActivation';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Paginated, type SelectOption, type TableColumn, type TableQuery } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Cog, Handshake, Plus } from 'lucide-vue-next';
import { computed } from 'vue';

interface VehicleRow {
    id: number;
    plate: string;
    internal_number: string | null;
    brand: string;
    line: string;
    model_year: number;
    class_label: string;
    color: string;
    owner_type: string;
    owner_type_label: string;
    owner_name: string | null;
    current_mileage: number;
    expired_documents_count: number;
    expiring_documents_count: number;
    is_active: boolean;
    can: { update: boolean; toggle_active: boolean };
}

const props = defineProps<{
    vehicles: Paginated<VehicleRow>;
    query: TableQuery;
    ownerTypes: SelectOption[];
    classes: SelectOption[];
    can: { create: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Vehículos', href: '/vehicles' }];

const showingInactive = computed(() => props.query.status === 'inactive');

const columns: TableColumn[] = [
    { key: 'plate', label: 'Placa', sortable: true, primary: true },
    { key: 'vehicle', label: 'Vehículo' },
    { key: 'model_year', label: 'Modelo', sortable: true },
    { key: 'owner', label: 'Dueño' },
    { key: 'current_mileage', label: 'Kilometraje', sortable: true, align: 'right' },
    { key: 'documents', label: 'Documentos' },
];

/** Activos e inactivos son el mismo listado (en el legacy eran dos pantallas). */
const showStatus = (status: 'active' | 'inactive') => {
    router.get(route('vehicles.index'), status === 'inactive' ? { status } : {}, { preserveState: false });
};

const formatKm = (km: number) => `${new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(km)} km`;

const { can: hasPermission } = usePermissions();
const { deactivate, activate } = useVehicleActivation();
const selectClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm sm:w-auto';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Vehículos" />

        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <Heading title="Vehículos" description="Flota de la empresa y de sus proveedores y aliados." />
                <div class="flex flex-col gap-2 sm:flex-row">
                    <Button v-if="hasPermission('vehicle_parts.view')" variant="outline" as-child class="w-full sm:w-auto">
                        <Link :href="route('parts.index')">
                            <Cog />
                            Partes
                        </Link>
                    </Button>
                    <Button v-if="hasPermission('agreements.view')" variant="outline" as-child class="w-full sm:w-auto">
                        <Link :href="route('agreements.index')">
                            <Handshake />
                            Convenios
                        </Link>
                    </Button>
                    <Button v-if="can.create" as-child class="w-full sm:w-auto">
                        <Link :href="route('vehicles.create')">
                            <Plus />
                            Crear vehículo
                        </Link>
                    </Button>
                </div>
            </div>

            <div
                class="inline-flex w-full rounded-lg border border-sidebar-border/70 p-1 sm:w-auto"
                role="tablist"
                aria-label="Estado de los vehículos"
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
                :rows="vehicles"
                :columns="columns"
                :query="query"
                :url="route('vehicles.index')"
                :only="['vehicles', 'query']"
                :export-urls="{ xlsx: route('vehicles.export', { format: 'xlsx' }), pdf: route('vehicles.export', { format: 'pdf' }) }"
                search-placeholder="Buscar por placa, número interno, marca, línea o dueño"
                :empty-text="showingInactive ? 'No hay vehículos inactivos.' : 'No hay vehículos que coincidan con la búsqueda.'"
            >
                <template #filters="{ query: state, apply }">
                    <select
                        :value="state.owner_type ?? ''"
                        :class="selectClass"
                        aria-label="Tipo de dueño"
                        @change="apply({ owner_type: ($event.target as HTMLSelectElement).value || null })"
                    >
                        <option value="">Todos los dueños</option>
                        <option v-for="type in ownerTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                    </select>
                    <select
                        :value="state.class ?? ''"
                        :class="selectClass"
                        aria-label="Clase de vehículo"
                        @change="apply({ class: ($event.target as HTMLSelectElement).value || null })"
                    >
                        <option value="">Todas las clases</option>
                        <option v-for="vehicleClass in classes" :key="vehicleClass.value" :value="vehicleClass.value">
                            {{ vehicleClass.label }}
                        </option>
                    </select>
                </template>

                <template #cell-plate="{ row }">
                    <Link :href="route('vehicles.show', { vehicle: row.id })" class="font-medium tabular-nums hover:underline">{{ row.plate }}</Link>
                    <span v-if="row.internal_number" class="block text-xs text-muted-foreground">Interno {{ row.internal_number }}</span>
                </template>
                <template #cell-vehicle="{ row }">
                    <span class="block">{{ row.brand }} {{ row.line }}</span>
                    <span class="block text-xs text-muted-foreground">{{ row.class_label }} · {{ row.color }}</span>
                </template>
                <template #cell-owner="{ row }">
                    <VehicleOwnerBadge :type="row.owner_type" :label="row.owner_type_label" />
                    <span v-if="row.owner_name" class="mt-1 block text-xs text-muted-foreground">{{ row.owner_name }}</span>
                </template>
                <template #cell-current_mileage="{ row }">
                    <span class="whitespace-nowrap tabular-nums">{{ formatKm(row.current_mileage) }}</span>
                </template>
                <template #cell-documents="{ row }">
                    <div class="flex flex-wrap gap-1">
                        <span
                            v-if="row.expired_documents_count > 0"
                            class="whitespace-nowrap rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-950 dark:text-red-200"
                        >
                            {{ row.expired_documents_count }} vencido{{ row.expired_documents_count === 1 ? '' : 's' }}
                        </span>
                        <span
                            v-if="row.expiring_documents_count > 0"
                            class="whitespace-nowrap rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-200"
                        >
                            {{ row.expiring_documents_count }} por vencer
                        </span>
                        <span v-if="row.expired_documents_count === 0 && row.expiring_documents_count === 0" class="text-xs text-muted-foreground"
                            >Al día</span
                        >
                    </div>
                </template>

                <template #actions="{ row, labeled }">
                    <ActionButton action="view" :labeled="labeled" :label="`Ver ${row.plate}`" :href="route('vehicles.show', { vehicle: row.id })" />
                    <ActionButton
                        v-if="row.can.update"
                        action="edit"
                        :labeled="labeled"
                        :label="`Editar ${row.plate}`"
                        :href="route('vehicles.edit', { vehicle: row.id })"
                    />
                    <template v-if="row.can.toggle_active">
                        <ActionButton
                            v-if="row.is_active"
                            action="deactivate"
                            :labeled="labeled"
                            :label="`Inhabilitar ${row.plate}`"
                            @click="deactivate(row)"
                        />
                        <ActionButton v-else action="activate" :labeled="labeled" :label="`Habilitar ${row.plate}`" @click="activate(row)" />
                    </template>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
