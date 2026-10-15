<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export interface TenantCompanyForm {
    name: string;
    nit: string;
    email: string;
    phone: string;
    address: string;
    city: string;
}

defineProps<{
    errors: Partial<Record<keyof TenantCompanyForm, string>>;
}>();

const form = defineModel<TenantCompanyForm>({ required: true });
</script>

<template>
    <div class="grid gap-6 md:grid-cols-2">
        <div class="grid gap-2 md:col-span-2">
            <Label for="name">Nombre de la empresa</Label>
            <Input id="name" v-model="form.name" required autofocus />
            <InputError :message="errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="nit">NIT</Label>
            <Input id="nit" v-model="form.nit" required placeholder="900123456-7" />
            <InputError :message="errors.nit" />
        </div>

        <div class="grid gap-2">
            <Label for="email">Correo de contacto</Label>
            <Input id="email" type="email" v-model="form.email" />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="phone">Teléfono</Label>
            <Input id="phone" v-model="form.phone" />
            <InputError :message="errors.phone" />
        </div>

        <div class="grid gap-2">
            <Label for="city">Ciudad</Label>
            <Input id="city" v-model="form.city" />
            <InputError :message="errors.city" />
        </div>

        <div class="grid gap-2 md:col-span-2">
            <Label for="address">Dirección</Label>
            <Input id="address" v-model="form.address" />
            <InputError :message="errors.address" />
        </div>
    </div>
</template>
