<script setup lang="ts">
import SubdomainField from '@/components/central/SubdomainField.vue';
import TenantCompanyFields from '@/components/central/TenantCompanyFields.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import CentralLayout from '@/layouts/CentralLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

interface TenantData {
    id: string;
    name: string;
    nit: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    city: string | null;
    subdomain: string | null;
}

const props = defineProps<{
    tenant: TenantData;
    tenantBaseDomain: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Empresas', href: route('central.tenants.index', undefined, false) },
    { title: props.tenant.name, href: route('central.tenants.show', { tenant: props.tenant.id }, false) },
    { title: 'Editar', href: route('central.tenants.edit', { tenant: props.tenant.id }, false) },
];

const form = useForm({
    name: props.tenant.name,
    nit: props.tenant.nit ?? '',
    email: props.tenant.email ?? '',
    phone: props.tenant.phone ?? '',
    address: props.tenant.address ?? '',
    city: props.tenant.city ?? '',
    subdomain: props.tenant.subdomain ?? '',
});

const submit = () => {
    form.put(route('central.tenants.update', { tenant: props.tenant.id }));
};
</script>

<template>
    <CentralLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Editar ${tenant.name}`" />

        <Heading title="Editar empresa" :description="tenant.name" />

        <form class="max-w-3xl space-y-8" @submit.prevent="submit">
            <TenantCompanyFields v-model="form" :errors="form.errors" />

            <div class="space-y-2">
                <SubdomainField v-model="form.subdomain" :base-domain="tenantBaseDomain" :error="form.errors.subdomain" />
                <p class="text-sm text-muted-foreground">Si cambias el subdominio, la dirección anterior deja de funcionar.</p>
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Guardar cambios
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="route('central.tenants.show', { tenant: tenant.id })">Cancelar</Link>
                </Button>
            </div>
        </form>
    </CentralLayout>
</template>
