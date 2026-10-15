<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SelectOption } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

interface RouteData {
    id: number;
    start_point: string;
    end_point: string;
    description: string | null;
    type: string;
    status: string;
}

const props = defineProps<{
    item: RouteData | null;
    types: SelectOption[];
    statuses: SelectOption[];
}>();

const title = computed(() => (props.item ? 'Editar ruta' : 'Crear ruta'));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Rutas', href: route('routes.index') },
    { title: title.value, href: props.item ? route('routes.edit', { route: props.item.id }) : route('routes.create') },
]);

const form = useForm({
    start_point: props.item?.start_point ?? '',
    end_point: props.item?.end_point ?? '',
    description: props.item?.description ?? '',
    type: props.item?.type ?? 'urban',
    status: props.item?.status ?? 'active',
});

/** El nombre de la ruta se arma solo con el inicio y el fin, como en el legacy. */
const previewName = computed(() => `${form.start_point.trim() || 'Inicio'} - ${form.end_point.trim() || 'Fin'}`);

const submit = () => {
    if (props.item) {
        form.put(route('routes.update', { route: props.item.id }));
    } else {
        form.post(route('routes.store'));
    }
};

const selectClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="title" />

        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <Heading :title="title" description="Todos los campos son obligatorios." />

            <form class="flex max-w-3xl flex-col gap-8" @submit.prevent="submit">
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="start_point">Punto de inicio</Label>
                        <Input id="start_point" v-model="form.start_point" required maxlength="100" placeholder="Ejemplo: Bocagrande" />
                        <InputError :message="form.errors.start_point" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="end_point">Punto de fin</Label>
                        <Input id="end_point" v-model="form.end_point" required maxlength="100" placeholder="Ejemplo: Turbaco" />
                        <InputError :message="form.errors.end_point" />
                    </div>
                    <p class="text-sm text-muted-foreground md:col-span-2">
                        Nombre de la ruta: <span class="font-medium text-foreground">{{ previewName }}</span>
                    </p>
                    <div class="grid gap-2">
                        <Label for="type">Tipo de ruta</Label>
                        <select id="type" v-model="form.type" :class="selectClass" required>
                            <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </select>
                        <InputError :message="form.errors.type" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="status">Estado</Label>
                        <select id="status" v-model="form.status" :class="selectClass" required>
                            <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                        </select>
                        <InputError :message="form.errors.status" />
                    </div>
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="description">Descripción</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            required
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            placeholder="Ingrese la descripción…"
                        />
                        <InputError :message="form.errors.description" />
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                    <Button variant="outline" as-child class="w-full sm:w-auto">
                        <Link :href="route('routes.index')">Cancelar</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing" class="w-full sm:w-auto">
                        <LoaderCircle v-if="form.processing" class="animate-spin" />
                        Guardar ruta
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
