<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import UserAccessFields from '@/components/UserAccessFields.vue';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

export interface InitialUser {
    name: string;
    username: string;
    email: string | null;
    invitation_pending: boolean;
    invited_at: string | null;
}

const props = defineProps<{
    tenantId: string;
    user: InitialUser;
}>();

const form = useForm({
    name: props.user.name,
    username: props.user.username,
    email: props.user.email ?? '',
    send_invitation: false,
    password: '',
    password_confirmation: '',
});

const save = () => {
    form.put(route('central.tenants.initial-user.update', { tenant: props.tenantId }), {
        preserveScroll: true,
        onSuccess: () => {
            form.send_invitation = false;
            form.password = '';
            form.password_confirmation = '';
            form.defaults();
        },
        onError: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <section class="max-w-3xl space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <HeadingSmall title="Usuario inicial" description="Administrador creado en el alta de la empresa." />
            <span
                v-if="user.invitation_pending"
                class="rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-200"
            >
                Invitación pendiente
            </span>
            <span v-else class="rounded-md bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-950 dark:text-green-200">
                Cuenta activa
            </span>
        </div>

        <form class="space-y-6 rounded-xl border border-sidebar-border/70 p-6" @submit.prevent="save">
            <div class="grid gap-6 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="initial_user_name">Nombre</Label>
                    <Input id="initial_user_name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="initial_user_username">Usuario</Label>
                    <Input id="initial_user_username" v-model="form.username" required autocapitalize="off" />
                    <InputError :message="form.errors.username" />
                </div>
                <div class="grid gap-2">
                    <Label for="initial_user_email">Correo</Label>
                    <Input id="initial_user_email" type="email" v-model="form.email" :required="form.send_invitation" />
                    <InputError :message="form.errors.email" />
                </div>
            </div>

            <p v-if="user.invitation_pending && user.invited_at" class="text-sm text-muted-foreground">
                Última invitación enviada: {{ user.invited_at }}. El enlace vence a los 7 días.
            </p>

            <UserAccessFields
                id-prefix="initial_user"
                v-model:send-invitation="form.send_invitation"
                v-model:password="form.password"
                v-model:password-confirmation="form.password_confirmation"
                invitation-label="Enviar invitación por correo al guardar"
                invitation-hint="Le llega un enlace nuevo (al correo de arriba) para definir su contraseña. Útil si le cambiaste el correo o no recibió el anterior."
                :password-error="form.errors.password"
                :password-confirmation-error="form.errors.password_confirmation"
            />

            <Button type="submit" :disabled="form.processing || !form.isDirty">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                Guardar usuario
            </Button>
        </form>
    </section>
</template>
