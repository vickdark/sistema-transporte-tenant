<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const props = defineProps<{
    name: string;
    email: string;
    submitUrl: string;
}>();

const form = useForm({
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(props.submitUrl, {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase :title="`Bienvenido, ${name}`" description="Define tu contraseña para activar tu cuenta">
        <Head title="Aceptar invitación" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Correo</Label>
                    <Input id="email" type="email" :model-value="email" readonly />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Contraseña</Label>
                    <Input id="password" type="password" required autofocus autocomplete="new-password" v-model="form.password" />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirmar contraseña</Label>
                    <Input id="password_confirmation" type="password" required autocomplete="new-password" v-model="form.password_confirmation" />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <Button type="submit" class="mt-4 w-full" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Activar cuenta
                </Button>
            </div>
        </form>
    </AuthBase>
</template>
