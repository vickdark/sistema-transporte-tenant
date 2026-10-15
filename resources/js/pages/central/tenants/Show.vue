<script setup lang="ts">
import DeleteTenantSection from '@/components/central/DeleteTenantSection.vue';
import StatusBadge from '@/components/central/StatusBadge.vue';
import TenantInitialUserCard, { type InitialUser } from '@/components/central/TenantInitialUserCard.vue';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/composables/useConfirm';
import CentralLayout from '@/layouts/CentralLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink, Pencil } from 'lucide-vue-next';
import { computed } from 'vue';

interface TenantDetail {
    id: string;
    name: string;
    nit: string | null;
    status: string;
    status_label: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    city: string | null;
    subdomain: string | null;
    suspended_at: string | null;
    users_count: number;
    created_at: string | null;
    url: string | null;
}

const props = defineProps<{
    tenant: TenantDetail;
    initialUser: InitialUser | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Empresas', href: route('central.tenants.index', undefined, false) },
    { title: props.tenant.name, href: route('central.tenants.show', { tenant: props.tenant.id }, false) },
];

const details = computed(() => [
    { label: 'NIT', value: props.tenant.nit },
    { label: 'Subdominio', value: props.tenant.subdomain },
    { label: 'Correo', value: props.tenant.email },
    { label: 'Teléfono', value: props.tenant.phone },
    { label: 'Ciudad', value: props.tenant.city },
    { label: 'Dirección', value: props.tenant.address },
    { label: 'Usuarios', value: String(props.tenant.users_count) },
    { label: 'Creada', value: props.tenant.created_at },
]);

const isSuspended = computed(() => props.tenant.status === 'suspended');

const { confirm } = useConfirm();

const toggleStatus = async () => {
    const confirmed = await confirm(
        isSuspended.value
            ? { title: `¿Reactivar ${props.tenant.name}?`, text: 'Sus usuarios podrán volver a ingresar.', confirmText: 'Reactivar' }
            : {
                  title: `¿Suspender ${props.tenant.name}?`,
                  text: 'Sus usuarios no podrán ingresar hasta que se reactive. No se borra ningún dato.',
                  confirmText: 'Suspender',
                  destructive: true,
              },
    );

    if (!confirmed) {
        return;
    }

    router.post(
        route(isSuspended.value ? 'central.tenants.reactivate' : 'central.tenants.suspend', { tenant: props.tenant.id }),
        {},
        { preserveScroll: true },
    );
};
</script>

<template>
    <CentralLayout :breadcrumbs="breadcrumbs">
        <Head :title="tenant.name" />

        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-1">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-xl font-semibold tracking-tight">{{ tenant.name }}</h2>
                    <StatusBadge :status="tenant.status" :label="tenant.status_label" />
                </div>
                <p v-if="tenant.suspended_at" class="text-sm text-muted-foreground">Suspendida desde {{ tenant.suspended_at }}</p>
                <a
                    v-if="tenant.url"
                    :href="tenant.url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-1 break-all text-sm text-muted-foreground hover:underline"
                >
                    {{ tenant.url }}
                    <ExternalLink class="size-3" />
                </a>
            </div>
            <div class="grid grid-cols-2 gap-2 sm:flex">
                <Button variant="outline" as-child>
                    <Link :href="route('central.tenants.edit', { tenant: tenant.id })">
                        <Pencil />
                        Editar
                    </Link>
                </Button>
                <Button :variant="isSuspended ? 'default' : 'destructive'" @click="toggleStatus">
                    {{ isSuspended ? 'Reactivar' : 'Suspender' }}
                </Button>
            </div>
        </div>

        <dl class="grid grid-cols-1 gap-x-8 gap-y-4 rounded-xl border border-sidebar-border/70 p-4 min-[420px]:grid-cols-2 sm:p-6 xl:grid-cols-3">
            <div v-for="item in details" :key="item.label">
                <dt class="text-xs uppercase text-muted-foreground">{{ item.label }}</dt>
                <dd class="mt-1 break-words text-sm">{{ item.value || '—' }}</dd>
            </div>
        </dl>

        <TenantInitialUserCard v-if="initialUser" :key="`${initialUser.username}-${initialUser.email}`" :tenant-id="tenant.id" :user="initialUser" />

        <DeleteTenantSection v-if="tenant.subdomain" :tenant-id="tenant.id" :tenant-name="tenant.name" :subdomain="tenant.subdomain" />
    </CentralLayout>
</template>
