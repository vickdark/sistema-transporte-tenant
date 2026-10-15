<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('central.login.store'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase title="Panel central" description="Ingresa con tu cuenta de administrador de la plataforma">
        <Head title="Ingresar" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Correo</Label>
                    <Input id="email" type="email" required autofocus autocomplete="email" v-model="form.email" placeholder="correo@ejemplo.com" />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Contraseña</Label>
                    <Input id="password" type="password" required autocomplete="current-password" v-model="form.password" />
                    <InputError :message="form.errors.password" />
                </div>

                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" v-model:checked="form.remember" />
                    <span>Recordarme</span>
                </Label>

                <Button type="submit" class="mt-4 w-full" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Ingresar
                </Button>
            </div>
        </form>
    </AuthBase>
</template>
