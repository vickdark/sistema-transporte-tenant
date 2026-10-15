<script setup lang="ts">
import SubdomainField from '@/components/central/SubdomainField.vue';
import TenantCompanyFields from '@/components/central/TenantCompanyFields.vue';
import UserAccessFields from '@/components/central/UserAccessFields.vue';
import Heading from '@/components/Heading.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import CentralLayout from '@/layouts/CentralLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    tenantBaseDomain: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Empresas', href: route('central.tenants.index', undefined, false) },
    { title: 'Nueva empresa', href: route('central.tenants.create', undefined, false) },
];

const form = useForm({
    name: '',
    nit: '',
    email: '',
    phone: '',
    address: '',
    city: '',
    subdomain: '',
    admin_name: '',
    admin_email: '',
    send_invitation: true,
    admin_password: '',
    admin_password_confirmation: '',
});

const submit = () => {
    form.post(route('central.tenants.store'), {
        onError: () => form.reset('admin_password', 'admin_password_confirmation'),
    });
};
</script>

<template>
    <CentralLayout :breadcrumbs="breadcrumbs">
        <Head title="Nueva empresa" />

        <Heading title="Nueva empresa" description="Crea el inquilino, su subdominio y su primer usuario administrador." />

        <form class="max-w-3xl space-y-10" @submit.prevent="submit">
            <section class="space-y-6">
                <HeadingSmall title="Datos de la empresa" />
                <TenantCompanyFields v-model="form" :errors="form.errors" />
            </section>

            <section class="space-y-6">
                <HeadingSmall title="Acceso" description="Dirección con la que los usuarios de la empresa ingresan al sistema." />
                <SubdomainField v-model="form.subdomain" :base-domain="tenantBaseDomain" :error="form.errors.subdomain" />
            </section>

            <section class="space-y-6">
                <HeadingSmall title="Administrador inicial" description="Tendrá todos los permisos de la empresa." />
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="admin_name">Nombre</Label>
                        <Input id="admin_name" v-model="form.admin_name" required />
                        <InputError :message="form.errors.admin_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="admin_email">Correo</Label>
                        <Input id="admin_email" type="email" v-model="form.admin_email" required />
                        <InputError :message="form.errors.admin_email" />
                    </div>
                </div>
                <UserAccessFields
                    id-prefix="admin"
                    v-model:send-invitation="form.send_invitation"
                    v-model:password="form.admin_password"
                    v-model:password-confirmation="form.admin_password_confirmation"
                    invitation-label="Enviar invitación por correo"
                    invitation-hint="Recibirá un enlace (válido 7 días) para definir su contraseña. Si lo desmarcas, defines la contraseña aquí y la cuenta queda activa de una vez."
                    password-required
                    :password-error="form.errors.admin_password"
                    :password-confirmation-error="form.errors.admin_password_confirmation"
                />
            </section>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Crear empresa
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="route('central.tenants.index')">Cancelar</Link>
                </Button>
            </div>
        </form>
    </CentralLayout>
</template>
