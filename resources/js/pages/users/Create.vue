<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import UserForm, { type UserFormData } from '@/components/users/UserForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SelectOption } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{
    roles: SelectOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Usuarios', href: '/users' },
    { title: 'Nuevo usuario', href: '/users/create' },
];

const form = useForm<UserFormData>({
    name: '',
    username: '',
    email: '',
    role_id: null,
    send_invitation: false,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('users.store'), {
        onError: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Nuevo usuario" />

        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <Heading title="Nuevo usuario" description="El usuario ingresa con su nombre de usuario o su correo." />
            <UserForm v-model:form="form" :roles="roles" is-creating submit-label="Crear usuario" @submit="submit" />
        </div>
    </AppLayout>
</template>
