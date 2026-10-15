<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface VehicleOption {
    id: number;
    plate: string;
    description: string;
}

const props = defineProps<{ vehicles: VehicleOption[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Vehículos', href: route('vehicles.index') },
    { title: 'Partes', href: route('parts.index') },
    { title: 'Crear parte', href: route('parts.create') },
];

const form = useForm({
    name: '',
    maintenance_interval_km: '' as number | '',
    vehicles: [] as number[],
});

const search = ref('');
const visibleVehicles = computed(() => {
    const term = search.value.trim().toLowerCase();

    return term === '' ? props.vehicles : props.vehicles.filter((vehicle) => `${vehicle.plate} ${vehicle.description}`.toLowerCase().includes(term));
});

const allSelected = computed(() => props.vehicles.length > 0 && form.vehicles.length === props.vehicles.length);
const toggleAll = () => (form.vehicles = allSelected.value ? [] : props.vehicles.map((vehicle) => vehicle.id));

const vehiclesError = computed(() => form.errors.vehicles ?? Object.entries(form.errors).find(([key]) => key.startsWith('vehicles.'))?.[1]);

const submit = () => form.post(route('parts.store'));
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Crear parte" />

        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <Heading title="Crear parte" description="La parte se agrega al catálogo y a los vehículos activos que elijas." />

            <form class="flex max-w-3xl flex-col gap-6" @submit.prevent="submit">
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="name">Nombre de la parte *</Label>
                        <Input id="name" v-model="form.name" required maxlength="100" placeholder="Ej: Filtro de combustible" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="maintenance_interval_km">Kilometraje entre mantenimientos *</Label>
                        <Input
                            id="maintenance_interval_km"
                            v-model="form.maintenance_interval_km"
                            type="number"
                            min="1"
                            step="1"
                            required
                            placeholder="Ej: 10000"
                        />
                        <InputError :message="form.errors.maintenance_interval_km" />
                    </div>
                </div>

                <fieldset class="flex flex-col gap-3">
                    <legend class="mb-2 text-sm font-medium">Aplicar a vehículos *</legend>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" class="accent-primary" :checked="allSelected" @change="toggleAll" />
                            Seleccionar todos ({{ vehicles.length }})
                        </label>
                        <div class="relative sm:w-64">
                            <Search class="pointer-events-none absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                            <Input v-model="search" class="pl-8" placeholder="Filtrar por placa" aria-label="Filtrar vehículos" />
                        </div>
                    </div>
                    <div class="max-h-96 overflow-y-auto rounded-xl border border-sidebar-border/70">
                        <label
                            v-for="vehicle in visibleVehicles"
                            :key="vehicle.id"
                            class="flex cursor-pointer items-center gap-3 border-b border-sidebar-border/70 px-4 py-2 text-sm last:border-b-0 hover:bg-muted/50"
                        >
                            <input v-model="form.vehicles" type="checkbox" class="accent-primary" :value="vehicle.id" />
                            <span class="font-medium tabular-nums">{{ vehicle.plate }}</span>
                            <span class="truncate text-muted-foreground">{{ vehicle.description }}</span>
                        </label>
                        <p v-if="visibleVehicles.length === 0" class="px-4 py-6 text-center text-sm text-muted-foreground">
                            No hay vehículos activos.
                        </p>
                    </div>
                    <p class="text-xs text-muted-foreground">{{ form.vehicles.length }} seleccionado(s).</p>
                    <InputError :message="vehiclesError" />
                </fieldset>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                    <Button variant="outline" as-child class="w-full sm:w-auto">
                        <Link :href="route('parts.index')">Cancelar</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing" class="w-full sm:w-auto">
                        <LoaderCircle v-if="form.processing" class="animate-spin" />
                        Crear parte
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
