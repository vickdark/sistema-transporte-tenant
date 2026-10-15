<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import UserForm, { type UserFormData } from '@/components/users/UserForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SelectOption } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

interface EditableUser {
    id: number;
    name: string;
    username: string;
    email: string | null;
    role_id: number | null;
    invitation_pending: boolean;
}

const props = defineProps<{
    user: EditableUser;
    roles: SelectOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Usuarios', href: '/users' },
    { title: props.user.name, href: `/users/${props.user.id}/edit` },
];

const form = useForm<UserFormData>({
    name: props.user.name,
    username: props.user.username,
    email: props.user.email ?? '',
    role_id: props.user.role_id,
    send_invitation: false,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.put(route('users.update', { user: props.user.id }), {
        onError: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Editar ${user.name}`" />

        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading title="Editar usuario" :description="user.name" />
                <span
                    v-if="user.invitation_pending"
                    class="rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-200"
                >
                    Invitación pendiente
                </span>
            </div>
            <UserForm v-model:form="form" :roles="roles" :is-creating="false" submit-label="Guardar cambios" @submit="submit" />
        </div>
    </AppLayout>
</template>
