<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import ActionButton from '@/components/table/ActionButton.vue';
import VehicleDocumentCard from '@/components/vehicles/VehicleDocumentCard.vue';
import VehicleOwnerBadge from '@/components/vehicles/VehicleOwnerBadge.vue';
import VehiclePhotoGallery from '@/components/vehicles/VehiclePhotoGallery.vue';
import { useVehicleActivation } from '@/composables/useVehicleActivation';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { type VehicleDocumentSlot, type VehiclePhotoData } from '@/types/vehicles';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed } from 'vue';

interface VehicleDetail {
    id: number;
    plate: string;
    internal_number: string | null;
    current_mileage: number;
    registration_date: string;
    registration_place: string;
    model_year: number;
    brand: string;
    line: string;
    class_label: string;
    engine_number: string;
    chassis_number: string;
    color: string;
    displacement: number | null;
    fuel_label: string | null;
    owner_type: string;
    owner_type_label: string;
    owners: { kind: string; name: string; url: string | null }[];
    agreement: { name: string; nit: string; url: string | null } | null;
    is_active: boolean;
}

const props = defineProps<{
    item: VehicleDetail;
    documents: VehicleDocumentSlot[];
    photos: VehiclePhotoData[];
    parts: { name: string; maintenance_interval_km: number; url: string | null }[];
    limits: { photos: number; photo_mb: number; document_mb: number };
    can: { update: boolean; toggle_active: boolean };
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Vehículos', href: route('vehicles.index') },
    { title: props.item.plate, href: route('vehicles.show', { vehicle: props.item.id }) },
]);

const formatNumber = (value: number) => new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(value);
const formatDate = (date: string) => new Date(`${date}T00:00:00`).toLocaleDateString('es-CO', { dateStyle: 'medium' });

const details = computed(() => [
    { label: 'Marca', value: props.item.brand },
    { label: 'Línea', value: props.item.line },
    { label: 'Modelo', value: String(props.item.model_year) },
    { label: 'Clase', value: props.item.class_label },
    { label: 'Color', value: props.item.color },
    { label: 'Combustible', value: props.item.fuel_label },
    { label: 'Cilindraje', value: props.item.displacement ? `${formatNumber(props.item.displacement)} cc` : null },
    { label: 'Número interno', value: props.item.internal_number },
    { label: 'Número de motor', value: props.item.engine_number },
    { label: 'Número de chasis', value: props.item.chassis_number },
    { label: 'Fecha de matrícula', value: formatDate(props.item.registration_date) },
    { label: 'Lugar de matrícula', value: props.item.registration_place },
    { label: 'Kilometraje actual', value: `${formatNumber(props.item.current_mileage)} km` },
]);

const documentAlerts = computed(() => ({
    expired: props.documents.filter((document) => document.current?.status === 'expired').length,
    expiring: props.documents.filter((document) => document.current?.status === 'expiring').length,
}));

const { deactivate, activate } = useVehicleActivation();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Vehículo ${item.plate}`" />

        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-2">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-xl font-semibold tabular-nums tracking-tight">{{ item.plate }}</h2>
                        <VehicleOwnerBadge :type="item.owner_type" :label="item.owner_type_label" />
                        <span
                            v-if="!item.is_active"
                            class="whitespace-nowrap rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        >
                            Inactivo
                        </span>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        <template v-if="item.owners.length === 0">De la empresa</template>
                        <template v-for="(owner, index) in item.owners" :key="owner.kind">
                            <template v-if="index > 0"> · </template>
                            {{ owner.kind }}:
                            <Link v-if="owner.url" :href="owner.url" class="font-medium text-foreground hover:underline">{{ owner.name }}</Link>
                            <span v-else class="font-medium text-foreground">{{ owner.name }}</span>
                        </template>
                        <template v-if="item.agreement">
                            · Convenio:
                            <Link v-if="item.agreement.url" :href="item.agreement.url" class="font-medium text-foreground hover:underline">{{
                                item.agreement.name
                            }}</Link>
                            <span v-else class="font-medium text-foreground">{{ item.agreement.name }}</span>
                        </template>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <ActionButton
                        v-if="can.update"
                        action="edit"
                        labeled
                        :label="`Editar ${item.plate}`"
                        :href="route('vehicles.edit', { vehicle: item.id })"
                    />
                    <template v-if="can.toggle_active">
                        <ActionButton
                            v-if="item.is_active"
                            action="deactivate"
                            labeled
                            :label="`Inhabilitar ${item.plate}`"
                            @click="deactivate(item)"
                        />
                        <ActionButton v-else action="activate" labeled :label="`Habilitar ${item.plate}`" @click="activate(item)" />
                    </template>
                </div>
            </div>

            <dl
                class="grid grid-cols-1 gap-x-8 gap-y-4 rounded-xl border border-sidebar-border/70 p-4 min-[420px]:grid-cols-2 sm:p-6 lg:grid-cols-3 xl:grid-cols-4"
            >
                <div v-for="detail in details" :key="detail.label" class="min-w-0">
                    <dt class="text-xs uppercase text-muted-foreground">{{ detail.label }}</dt>
                    <dd class="mt-1 break-words text-sm">{{ detail.value || '—' }}</dd>
                </div>
            </dl>

            <section class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    <HeadingSmall title="Documentos" />
                    <span
                        v-if="documentAlerts.expired > 0"
                        class="whitespace-nowrap rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-950 dark:text-red-200"
                    >
                        {{ documentAlerts.expired }} vencido{{ documentAlerts.expired === 1 ? '' : 's' }}
                    </span>
                    <span
                        v-if="documentAlerts.expiring > 0"
                        class="whitespace-nowrap rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-200"
                    >
                        {{ documentAlerts.expiring }} por vencer
                    </span>
                </div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <VehicleDocumentCard
                        v-for="document in documents"
                        :key="document.type"
                        :vehicle-id="item.id"
                        :document="document"
                        :can-update="can.update"
                        :max-file-mb="limits.document_mb"
                    />
                </div>
            </section>

            <section class="flex flex-col gap-4">
                <HeadingSmall title="Fotos" />
                <VehiclePhotoGallery
                    :vehicle-id="item.id"
                    :plate="item.plate"
                    :photos="photos"
                    :can-update="can.update"
                    :max="limits.photos"
                    :max-file-mb="limits.photo_mb"
                />
            </section>

            <section class="flex flex-col gap-4">
                <HeadingSmall title="Partes" description="Cada cuántos kilómetros se le hace mantenimiento a cada parte." />
                <ul v-if="parts.length > 0" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <li
                        v-for="part in parts"
                        :key="part.name"
                        class="flex items-center justify-between gap-3 rounded-lg border border-sidebar-border/70 px-3 py-2 text-sm"
                    >
                        <Link v-if="part.url" :href="part.url" class="min-w-0 break-words hover:underline">{{ part.name }}</Link>
                        <span v-else class="min-w-0 break-words">{{ part.name }}</span>
                        <span class="whitespace-nowrap tabular-nums text-muted-foreground">{{ formatNumber(part.maintenance_interval_km) }} km</span>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">El vehículo no tiene partes registradas.</p>
            </section>

            <div>
                <Link :href="route('vehicles.index')" class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="size-4" />
                    Regresar a vehículos
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
