<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import UserAccessFields from '@/components/UserAccessFields.vue';
import { type SelectOption } from '@/types';
import { type InertiaForm, Link } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

export interface UserFormData {
    name: string;
    username: string;
    email: string;
    role_id: number | null;
    send_invitation: boolean;
    password: string;
    password_confirmation: string;
}

defineProps<{
    roles: SelectOption[];
    isCreating: boolean;
    submitLabel: string;
}>();

const form = defineModel<InertiaForm<UserFormData>>('form', { required: true });

const emit = defineEmits<{ submit: [] }>();
</script>

<template>
    <form class="max-w-3xl space-y-8" @submit.prevent="emit('submit')">
        <div class="grid gap-6 md:grid-cols-2">
            <div class="grid gap-2 md:col-span-2">
                <Label for="name">Nombre completo</Label>
                <Input id="name" v-model="form.name" required autofocus />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="username">Usuario</Label>
                <Input id="username" v-model="form.username" required autocapitalize="off" placeholder="juan.perez" />
                <p class="text-sm text-muted-foreground">Con él ingresa al sistema. Minúsculas, números, punto, guion.</p>
                <InputError :message="form.errors.username" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Correo (opcional)</Label>
                <Input id="email" type="email" v-model="form.email" />
                <p class="text-sm text-muted-foreground">También puede ingresar con él y recuperar su contraseña.</p>
                <InputError :message="form.errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="role_id">Rol</Label>
                <select
                    id="role_id"
                    v-model="form.role_id"
                    required
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                >
                    <option :value="null" disabled>Selecciona un rol</option>
                    <option v-for="role in roles" :key="role.value" :value="Number(role.value)">{{ role.label }}</option>
                </select>
                <InputError :message="form.errors.role_id" />
            </div>
        </div>

        <UserAccessFields
            id-prefix="user"
            v-model:send-invitation="form.send_invitation"
            v-model:password="form.password"
            v-model:password-confirmation="form.password_confirmation"
            :invitation-label="isCreating ? 'Enviar invitación por correo' : 'Enviar invitación por correo al guardar'"
            invitation-hint="Le llega un enlace (válido 7 días) para que defina su contraseña. Requiere correo."
            :password-required="isCreating"
            :password-error="form.errors.password"
            :password-confirmation-error="form.errors.password_confirmation"
        />
        <InputError :message="(form.errors as Record<string, string>).role" />

        <div class="flex items-center gap-3">
            <Button type="submit" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                {{ submitLabel }}
            </Button>
            <Button variant="ghost" as-child>
                <Link :href="route('users.index')">Cancelar</Link>
            </Button>
        </div>
    </form>
</template>
