<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import ActionButton from '@/components/table/ActionButton.vue';
import ThirdPartyTypeBadge from '@/components/ThirdPartyTypeBadge.vue';
import { type ThirdPartyKind, useThirdPartyLink } from '@/composables/useThirdPartyLink';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, CarFront, IdCard } from 'lucide-vue-next';
import { computed } from 'vue';

interface Item {
    id: number;
    name: string;
    nit: string | null;
    phone: string | null;
    address: string | null;
    email: string | null;
    is_mixed: boolean;
    twin: { name: string; url: string | null } | null;
    users_count: number;
}

const props = defineProps<{
    kind: ThirdPartyKind;
    item: Item;
    /** Sus vehículos; null si el usuario no puede ver vehículos. */
    vehicles: { id: number; plate: string; description: string; is_active: boolean }[] | null;
    urls: { index: string; show: string; edit: string; convert: string; revert: string };
    can: { update: boolean; link: boolean };
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: props.kind.plural, href: props.urls.index },
    { title: props.item.name, href: props.urls.show },
]);

const details = computed(() => [
    { label: props.kind.nit_label, value: props.item.nit },
    { label: 'Teléfono', value: props.item.phone },
    { label: 'Correo', value: props.item.email },
    { label: 'Dirección', value: props.item.address },
    { label: 'Usuarios con acceso', value: String(props.item.users_count) },
]);

const page = usePage();
const linkError = computed(() => (page.props.errors as Record<string, string>).link);

const { convert, revert } = useThirdPartyLink(props.kind);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="item.name" />

        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-2">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="break-words text-xl font-semibold tracking-tight">{{ item.name }}</h2>
                        <ThirdPartyTypeBadge :mixed="item.is_mixed" />
                    </div>
                    <p v-if="item.twin" class="text-sm text-muted-foreground">
                        También registrado como {{ kind.twin_singular }}:
                        <Link v-if="item.twin.url" :href="item.twin.url" class="font-medium text-foreground hover:underline">{{
                            item.twin.name
                        }}</Link>
                        <span v-else class="font-medium text-foreground">{{ item.twin.name }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <ActionButton v-if="can.update" action="edit" labeled :label="`Editar ${item.name}`" :href="urls.edit" />
                    <template v-if="can.link">
                        <ActionButton
                            v-if="item.is_mixed"
                            action="revert"
                            labeled
                            :label="`Revertir conversión de ${item.name}`"
                            @click="revert(item.name, urls)"
                        />
                        <ActionButton
                            v-else
                            action="convert"
                            labeled
                            :label="`Convertir en ${kind.twin_singular}`"
                            @click="convert(item.name, urls)"
                        />
                    </template>
                </div>
            </div>

            <InputError :message="linkError" />

            <dl class="grid grid-cols-1 gap-x-8 gap-y-4 rounded-xl border border-sidebar-border/70 p-4 min-[420px]:grid-cols-2 sm:p-6 xl:grid-cols-3">
                <div v-for="detail in details" :key="detail.label" class="min-w-0">
                    <dt class="text-xs uppercase text-muted-foreground">{{ detail.label }}</dt>
                    <dd class="mt-1 break-words text-sm">{{ detail.value || '—' }}</dd>
                </div>
            </dl>

            <div class="grid gap-4 lg:grid-cols-2">
                <section v-if="vehicles !== null" class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-4">
                    <HeadingSmall :title="`Vehículos del ${kind.singular} (${vehicles.length})`" />
                    <ul v-if="vehicles.length > 0" class="divide-y divide-sidebar-border/70">
                        <li v-for="vehicle in vehicles" :key="vehicle.id" class="flex items-center justify-between gap-3 py-2 text-sm">
                            <div class="min-w-0">
                                <Link :href="route('vehicles.show', { vehicle: vehicle.id })" class="font-medium tabular-nums hover:underline">{{
                                    vehicle.plate
                                }}</Link>
                                <span class="block truncate text-xs text-muted-foreground">{{ vehicle.description }}</span>
                            </div>
                            <span
                                v-if="!vehicle.is_active"
                                class="whitespace-nowrap rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                >Inactivo</span
                            >
                        </li>
                    </ul>
                    <p v-else class="flex items-center gap-2 text-sm text-muted-foreground">
                        <CarFront class="size-4 shrink-0" />
                        No tiene vehículos registrados.
                    </p>
                </section>
                <section class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-4">
                    <HeadingSmall :title="`Conductores del ${kind.singular}`" />
                    <p class="flex items-center gap-2 text-sm text-muted-foreground">
                        <IdCard class="size-4 shrink-0" />
                        Se mostrarán aquí cuando exista el módulo de empleados y conductores.
                    </p>
                </section>
            </div>

            <div>
                <Link :href="urls.index" class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="size-4" />
                    Regresar a {{ kind.plural.toLowerCase() }}
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
