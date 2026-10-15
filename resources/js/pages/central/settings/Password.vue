<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import CentralSettingsLayout from '@/layouts/central/CentralSettingsLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { TransitionRoot } from '@headlessui/vue';
import { Head, useForm } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Contraseña', href: route('central.settings.password.edit', undefined, false) }];

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('central.settings.password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: (errors) => {
            if (errors.password) {
                form.reset('password', 'password_confirmation');
            }

            if (errors.current_password) {
                form.reset('current_password');
            }
        },
    });
};
</script>

<template>
    <CentralSettingsLayout :breadcrumbs="breadcrumbs">
        <Head title="Contraseña" />

        <div class="space-y-6">
            <HeadingSmall title="Cambiar contraseña" description="Usa una contraseña larga y aleatoria para mantener tu cuenta segura" />

            <form @submit.prevent="updatePassword" class="space-y-6">
                <div class="grid gap-2">
                    <Label for="current_password">Contraseña actual</Label>
                    <Input id="current_password" v-model="form.current_password" type="password" autocomplete="current-password" />
                    <InputError :message="form.errors.current_password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Nueva contraseña</Label>
                    <Input id="password" v-model="form.password" type="password" autocomplete="new-password" />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirmar contraseña</Label>
                    <Input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <div class="flex items-center gap-4">
                    <Button :disabled="form.processing">Guardar contraseña</Button>

                    <TransitionRoot
                        :show="form.recentlySuccessful"
                        enter="transition ease-in-out"
                        enter-from="opacity-0"
                        leave="transition ease-in-out"
                        leave-to="opacity-0"
                    >
                        <p class="text-sm text-neutral-600">Guardada.</p>
                    </TransitionRoot>
                </div>
            </form>
        </div>
    </CentralSettingsLayout>
</template>
