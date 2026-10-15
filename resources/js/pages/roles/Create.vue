<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import RoleForm, { type PermissionGroup, type RoleFormData } from '@/components/roles/RoleForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{
    permissionGroups: PermissionGroup[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Roles y permisos', href: '/roles' },
    { title: 'Nuevo rol', href: '/roles/create' },
];

const form = useForm<RoleFormData>({
    name: '',
    description: '',
    permissions: [],
});

const submit = () => {
    form.post(route('roles.store'));
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Nuevo rol" />

        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <Heading title="Nuevo rol" />
            <RoleForm v-model:form="form" :permission-groups="permissionGroups" submit-label="Crear rol" @submit="submit" />
        </div>
    </AppLayout>
</template>
