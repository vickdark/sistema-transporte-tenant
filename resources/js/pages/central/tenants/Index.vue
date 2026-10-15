<script setup lang="ts">
import StatusBadge from '@/components/central/StatusBadge.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import CentralLayout from '@/layouts/CentralLayout.vue';
import { type BreadcrumbItem, type Paginated, type SelectOption } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { reactive } from 'vue';

interface TenantRow {
    id: string;
    name: string;
    nit: string | null;
    status: string;
    status_label: string;
    subdomain: string | null;
    created_at: string | null;
}

const props = defineProps<{
    tenants: Paginated<TenantRow>;
    filters: { search: string; status: string | null };
    statuses: SelectOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Empresas', href: route('central.tenants.index', undefined, false) }];

const filters = reactive({ search: props.filters.search, status: props.filters.status ?? '' });

const applyFilters = () => {
    router.get(route('central.tenants.index'), filters, { preserveState: true, replace: true });
};
</script>

<template>
    <CentralLayout :breadcrumbs="breadcrumbs">
        <Head title="Empresas" />

        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading title="Empresas" description="Inquilinos de la plataforma: cada empresa de transporte con sus dominios y usuarios." />
            <Button as-child>
                <Link :href="route('central.tenants.create')">
                    <Plus />
                    Nueva empresa
                </Link>
            </Button>
        </div>

        <form class="flex flex-wrap gap-3" @submit.prevent="applyFilters">
            <Input v-model="filters.search" class="max-w-xs" placeholder="Buscar por nombre o NIT" />
            <select
                v-model="filters.status"
                class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm"
                aria-label="Estado"
                @change="applyFilters"
            >
                <option value="">Todos los estados</option>
                <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
            </select>
            <Button type="submit" variant="outline">Buscar</Button>
        </form>

        <div class="overflow-x-auto rounded-xl border border-sidebar-border/70">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted/50 text-xs uppercase text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">Empresa</th>
                        <th class="px-4 py-3 font-medium">NIT</th>
                        <th class="px-4 py-3 font-medium">Subdominio</th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sidebar-border/70">
                    <tr v-for="tenant in tenants.data" :key="tenant.id" class="hover:bg-muted/30">
                        <td class="px-4 py-3 font-medium">
                            <Link :href="route('central.tenants.show', { tenant: tenant.id })" class="hover:underline">{{ tenant.name }}</Link>
                        </td>
                        <td class="px-4 py-3 tabular-nums">{{ tenant.nit ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ tenant.subdomain ?? '—' }}</td>
                        <td class="px-4 py-3"><StatusBadge :status="tenant.status" :label="tenant.status_label" /></td>
                    </tr>
                    <tr v-if="tenants.data.length === 0">
                        <td colspan="4" class="px-4 py-10 text-center text-muted-foreground">No hay empresas que coincidan con la búsqueda.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="tenants.last_page > 1" class="flex flex-wrap gap-1" aria-label="Paginación">
            <template v-for="link in tenants.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-state
                    class="rounded-md border px-3 py-1 text-sm"
                    :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                >
                    <span v-html="link.label" />
                </Link>
                <span v-else class="rounded-md border px-3 py-1 text-sm text-muted-foreground" v-html="link.label" />
            </template>
        </nav>
    </CentralLayout>
</template>
