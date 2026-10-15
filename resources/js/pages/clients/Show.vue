<script setup lang="ts">
import ActionButton from '@/components/table/ActionButton.vue';
import { useClientActivation } from '@/composables/useClientActivation';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed } from 'vue';

interface ClientDetail {
    id: number;
    name: string;
    type: 'person' | 'company';
    type_label: string;
    identification_type_label: string;
    identification: string;
    check_digit: string | null;
    branch_code: string | null;
    first_names: string | null;
    last_names: string | null;
    business_name: string | null;
    trade_name: string | null;
    city: string;
    address: string;
    phone: string;
    extension: string | null;
    is_active: boolean;
    owner: string | null;
    users_count: number;
}

const props = defineProps<{
    client: ClientDetail;
    can: { update: boolean; toggle_active: boolean };
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Clientes', href: route('clients.index') },
    { title: props.client.name, href: route('clients.show', { client: props.client.id }) },
]);

// Cada tipo muestra solo sus campos: una persona no tiene razón social ni una empresa nombres y apellidos.
const nameDetails = computed(() =>
    props.client.type === 'person'
        ? [
              { label: 'Nombres', value: props.client.first_names },
              { label: 'Apellidos', value: props.client.last_names },
          ]
        : [
              { label: 'Razón social', value: props.client.business_name },
              { label: 'Nombre comercial', value: props.client.trade_name },
          ],
);

const details = computed(() => [
    { label: 'Tipo de cliente', value: props.client.type_label },
    ...nameDetails.value,
    { label: 'Tipo de identificación', value: props.client.identification_type_label },
    {
        label: 'Identificación',
        value: props.client.check_digit ? `${props.client.identification}-${props.client.check_digit}` : props.client.identification,
    },
    { label: 'Código de sucursal', value: props.client.branch_code },
    { label: 'Ciudad', value: props.client.city },
    { label: 'Dirección', value: props.client.address },
    { label: 'Teléfono', value: props.client.extension ? `${props.client.phone} ext. ${props.client.extension}` : props.client.phone },
    { label: 'Cliente de', value: props.client.owner ?? 'La empresa' },
    { label: 'Usuarios con acceso', value: String(props.client.users_count) },
]);

const { deactivate, activate } = useClientActivation();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="client.name" />

        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex min-w-0 flex-wrap items-center gap-3">
                    <h2 class="break-words text-xl font-semibold tracking-tight">{{ client.name }}</h2>
                    <span
                        class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                        :class="
                            client.is_active
                                ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200'
                                : 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-200'
                        "
                    >
                        {{ client.is_active ? 'Activo' : 'Inactivo' }}
                    </span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <ActionButton
                        v-if="can.update"
                        action="edit"
                        labeled
                        :label="`Editar ${client.name}`"
                        :href="route('clients.edit', { client: client.id })"
                    />
                    <template v-if="can.toggle_active">
                        <ActionButton
                            v-if="client.is_active"
                            action="deactivate"
                            labeled
                            :label="`Inhabilitar ${client.name}`"
                            @click="deactivate(client)"
                        />
                        <ActionButton v-else action="activate" labeled :label="`Habilitar ${client.name}`" @click="activate(client)" />
                    </template>
                </div>
            </div>

            <dl class="grid grid-cols-1 gap-x-8 gap-y-4 rounded-xl border border-sidebar-border/70 p-4 min-[420px]:grid-cols-2 sm:p-6 xl:grid-cols-3">
                <div v-for="detail in details" :key="detail.label" class="min-w-0">
                    <dt class="text-xs uppercase text-muted-foreground">{{ detail.label }}</dt>
                    <dd class="mt-1 break-words text-sm">{{ detail.value || '—' }}</dd>
                </div>
            </dl>

            <div>
                <Link :href="route('clients.index')" class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="size-4" />
                    Regresar a clientes
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
