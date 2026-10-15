<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import ActionButton from '@/components/table/ActionButton.vue';
import { useAgreementDeletion } from '@/composables/useAgreementDeletion';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SearchSelectOption } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    item: { id: number; nit: string; business_name: string; vehicles: SearchSelectOption[] };
    can: { update: boolean; delete: boolean; view_vehicles: boolean };
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Vehículos', href: route('vehicles.index') },
    { title: 'Convenios', href: route('agreements.index') },
    { title: props.item.business_name, href: route('agreements.show', { agreement: props.item.id }) },
]);

const { remove } = useAgreementDeletion();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="item.business_name" />

        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <h2 class="break-words text-xl font-semibold tracking-tight">{{ item.business_name }}</h2>
                    <p class="text-sm tabular-nums text-muted-foreground">NIT {{ item.nit }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <ActionButton
                        v-if="can.update"
                        action="edit"
                        labeled
                        :label="`Editar ${item.business_name}`"
                        :href="route('agreements.edit', { agreement: item.id })"
                    />
                    <ActionButton v-if="can.delete" action="delete" labeled :label="`Eliminar ${item.business_name}`" @click="remove(item)" />
                </div>
            </div>

            <section class="flex flex-col gap-3">
                <HeadingSmall :title="`Vehículos en convenio (${item.vehicles.length})`" />
                <ul v-if="item.vehicles.length > 0" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <li v-for="vehicle in item.vehicles" :key="vehicle.value" class="rounded-lg border border-sidebar-border/70 px-3 py-2 text-sm">
                        <Link
                            v-if="can.view_vehicles"
                            :href="route('vehicles.show', { vehicle: vehicle.value })"
                            class="font-medium tabular-nums hover:underline"
                            >{{ vehicle.label }}</Link
                        >
                        <span v-else class="font-medium tabular-nums">{{ vehicle.label }}</span>
                        <span class="block truncate text-xs text-muted-foreground">{{ vehicle.description }}</span>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">El convenio no tiene vehículos.</p>
            </section>

            <div>
                <Link :href="route('agreements.index')" class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="size-4" />
                    Regresar a convenios
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
