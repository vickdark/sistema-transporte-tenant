<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type ThirdPartyKind } from '@/composables/useThirdPartyLink';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

interface Item {
    id: number;
    name: string;
    nit: string | null;
    phone: string | null;
    address: string | null;
    email: string | null;
}

const props = defineProps<{
    kind: ThirdPartyKind;
    item: Item | null;
    urls: { index: string; submit: string };
}>();

const title = computed(() => (props.item ? `Editar ${props.kind.singular}` : `Registrar ${props.kind.singular}`));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: props.kind.plural, href: props.urls.index },
    { title: props.item ? props.item.name : `Agregar ${props.kind.singular}`, href: props.urls.submit },
]);

const form = useForm({
    name: props.item?.name ?? '',
    nit: props.item?.nit ?? '',
    phone: props.item?.phone ?? '',
    address: props.item?.address ?? '',
    email: props.item?.email ?? '',
});

const submit = () => {
    if (props.item) {
        form.put(props.urls.submit);
    } else {
        form.post(props.urls.submit);
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="title" />

        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <Heading :title="title" :description="item ? item.name : `Llena los datos del ${kind.singular}. Solo el nombre es obligatorio.`" />

            <form class="flex max-w-3xl flex-col gap-8" @submit.prevent="submit">
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="name">Nombre</Label>
                        <Input id="name" v-model="form.name" required autofocus />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="nit">{{ kind.nit_label }}</Label>
                        <Input id="nit" v-model="form.nit" />
                        <InputError :message="form.errors.nit" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="phone">Teléfono</Label>
                        <Input id="phone" v-model="form.phone" inputmode="tel" />
                        <InputError :message="form.errors.phone" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="address">Dirección</Label>
                        <Input id="address" v-model="form.address" />
                        <InputError :message="form.errors.address" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="email">Correo</Label>
                        <Input id="email" v-model="form.email" type="email" />
                        <InputError :message="form.errors.email" />
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                    <Button variant="outline" as-child class="w-full sm:w-auto">
                        <Link :href="urls.index">Cancelar</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing" class="w-full sm:w-auto">
                        <LoaderCircle v-if="form.processing" class="animate-spin" />
                        Guardar {{ kind.singular }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
