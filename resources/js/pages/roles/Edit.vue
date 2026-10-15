<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import RoleForm, { type PermissionGroup, type RoleFormData } from '@/components/roles/RoleForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

interface EditableRole {
    id: number;
    name: string;
    description: string | null;
    permissions: string[];
    users_count: number;
}

const props = defineProps<{
    role: EditableRole;
    permissionGroups: PermissionGroup[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Roles y permisos', href: '/roles' },
    { title: props.role.name, href: `/roles/${props.role.id}/edit` },
];

const form = useForm<RoleFormData>({
    name: props.role.name,
    description: props.role.description ?? '',
    permissions: props.role.permissions,
});

const submit = () => {
    form.put(route('roles.update', { role: props.role.id }));
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Editar ${role.name}`" />

        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <Heading
                title="Editar rol"
                :description="
                    role.users_count > 0
                        ? `Lo tienen ${role.users_count} usuario(s): los cambios aplican de inmediato.`
                        : 'Ningún usuario tiene este rol todavía.'
                "
            />
            <RoleForm v-model:form="form" :permission-groups="permissionGroups" submit-label="Guardar cambios" @submit="submit" />
        </div>
    </AppLayout>
</template>
