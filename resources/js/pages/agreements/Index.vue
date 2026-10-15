<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import ActionButton from '@/components/table/ActionButton.vue';
import DataTable from '@/components/table/DataTable.vue';
import { Button } from '@/components/ui/button';
import { useAgreementDeletion } from '@/composables/useAgreementDeletion';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Paginated, type TableColumn, type TableQuery } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';

interface AgreementRow {
    id: number;
    nit: string;
    business_name: string;
    vehicles_count: number;
    plates: string[];
    can: { update: boolean; delete: boolean };
}

defineProps<{
    agreements: Paginated<AgreementRow>;
    query: TableQuery;
    can: { create: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Vehículos', href: route('vehicles.index') },
    { title: 'Convenios', href: route('agreements.index') },
];

const columns: TableColumn[] = [
    { key: 'business_name', label: 'Razón social', sortable: true, primary: true },
    { key: 'nit', label: 'NIT', sortable: true },
    { key: 'vehicles_count', label: 'Vehículos', sortable: true, align: 'right' },
    { key: 'plates', label: 'Placas' },
];

const { remove } = useAgreementDeletion();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Convenios" />

        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <Heading title="Convenios" description="Empresas de transporte con las que operan vehículos en convenio (salen en el FUEC)." />
                <Button v-if="can.create" as-child class="w-full sm:w-auto">
                    <Link :href="route('agreements.create')">
                        <Plus />
                        Crear convenio
                    </Link>
                </Button>
            </div>

            <DataTable
                :rows="agreements"
                :columns="columns"
                :query="query"
                :url="route('agreements.index')"
                :only="['agreements', 'query']"
                :export-urls="{ xlsx: route('agreements.export', { format: 'xlsx' }), pdf: route('agreements.export', { format: 'pdf' }) }"
                search-placeholder="Buscar por NIT, razón social o placa"
                empty-text="No hay convenios que coincidan con la búsqueda."
            >
                <template #cell-business_name="{ row }">
                    <Link :href="route('agreements.show', { agreement: row.id })" class="font-medium hover:underline">{{ row.business_name }}</Link>
                </template>
                <template #cell-nit="{ row }">
                    <span class="tabular-nums">{{ row.nit }}</span>
                </template>
                <template #cell-vehicles_count="{ row }">
                    <span class="tabular-nums">{{ row.vehicles_count }}</span>
                </template>
                <template #cell-plates="{ row }">
                    <div class="flex flex-wrap gap-1">
                        <span v-for="plate in row.plates" :key="plate" class="rounded bg-muted px-1.5 py-0.5 text-xs font-medium tabular-nums">{{
                            plate
                        }}</span>
                    </div>
                </template>

                <template #actions="{ row, labeled }">
                    <ActionButton
                        action="view"
                        :labeled="labeled"
                        :label="`Ver ${row.business_name}`"
                        :href="route('agreements.show', { agreement: row.id })"
                    />
                    <ActionButton
                        v-if="row.can.update"
                        action="edit"
                        :labeled="labeled"
                        :label="`Editar ${row.business_name}`"
                        :href="route('agreements.edit', { agreement: row.id })"
                    />
                    <ActionButton
                        v-if="row.can.delete"
                        action="delete"
                        :labeled="labeled"
                        :label="`Eliminar ${row.business_name}`"
                        @click="remove(row)"
                    />
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
