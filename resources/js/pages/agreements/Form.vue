<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SearchSelectOption } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface AgreementData {
    id: number;
    nit: string;
    business_name: string;
    vehicles: SearchSelectOption[];
}

const props = defineProps<{ item: AgreementData | null }>();

const isEditing = computed(() => props.item !== null);
const title = computed(() => (isEditing.value ? 'Editar convenio' : 'Crear convenio'));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Vehículos', href: route('vehicles.index') },
    { title: 'Convenios', href: route('agreements.index') },
    { title: title.value, href: isEditing.value ? route('agreements.edit', { agreement: props.item!.id }) : route('agreements.create') },
]);

const selected = ref<SearchSelectOption[]>([...(props.item?.vehicles ?? [])]);
const form = useForm({
    nit: props.item?.nit ?? '',
    business_name: props.item?.business_name ?? '',
    vehicles: selected.value.map((vehicle) => vehicle.value),
});

/** El buscador agrega el vehículo elegido a la lista y queda listo para el siguiente. */
const picked = ref<SearchSelectOption | null>(null);
watch(picked, (vehicle) => {
    if (vehicle && !selected.value.some((current) => current.value === vehicle.value)) {
        selected.value.push(vehicle);
    }

    picked.value = null;
});
watch(selected, (vehicles) => (form.vehicles = vehicles.map((vehicle) => vehicle.value)), { deep: true });

const removeVehicle = (vehicle: SearchSelectOption) => (selected.value = selected.value.filter((current) => current.value !== vehicle.value));

const vehiclesError = computed(() => form.errors.vehicles ?? Object.entries(form.errors).find(([key]) => key.startsWith('vehicles.'))?.[1]);

const submit = () => {
    if (isEditing.value) {
        form.put(route('agreements.update', { agreement: props.item!.id }));
    } else {
        form.post(route('agreements.store'));
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="title" />

        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <Heading :title="title" description="Los campos con * son obligatorios. Un vehículo puede estar en un solo convenio." />

            <form class="flex max-w-3xl flex-col gap-6" @submit.prevent="submit">
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="nit">NIT *</Label>
                        <Input id="nit" v-model="form.nit" required maxlength="20" inputmode="numeric" />
                        <InputError :message="form.errors.nit" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="business_name">Razón social *</Label>
                        <Input id="business_name" v-model="form.business_name" required maxlength="255" />
                        <InputError :message="form.errors.business_name" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="vehicle_search">Vehículos *</Label>
                    <SearchSelect
                        id="vehicle_search"
                        v-model="picked"
                        :search-url="route('agreements.search-vehicles')"
                        placeholder="Buscar vehículo activo por placa"
                    />
                    <ul v-if="selected.length > 0" class="divide-y divide-sidebar-border/70 rounded-xl border border-sidebar-border/70">
                        <li v-for="vehicle in selected" :key="vehicle.value" class="flex items-center justify-between gap-3 px-4 py-2 text-sm">
                            <div class="min-w-0">
                                <p class="font-medium tabular-nums">{{ vehicle.label }}</p>
                                <p v-if="vehicle.description" class="truncate text-xs text-muted-foreground">{{ vehicle.description }}</p>
                            </div>
                            <button
                                type="button"
                                class="shrink-0 rounded-md p-1 text-muted-foreground hover:bg-muted"
                                :aria-label="`Quitar ${vehicle.label}`"
                                @click="removeVehicle(vehicle)"
                            >
                                <X class="size-4" />
                            </button>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">Todavía no hay vehículos en el convenio.</p>
                    <InputError :message="vehiclesError" />
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                    <Button variant="outline" as-child class="w-full sm:w-auto">
                        <Link :href="route('agreements.index')">Cancelar</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing" class="w-full sm:w-auto">
                        <LoaderCircle v-if="form.processing" class="animate-spin" />
                        Guardar convenio
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
