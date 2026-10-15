<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useConfirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Check, Minus, Plus, Search, Trash2 } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

interface VehicleRow {
    id: number;
    plate: string;
    description: string;
    assignment: { id: number; maintenance_interval_km: number } | null;
}

const props = defineProps<{
    part: { id: number; name: string };
    vehicles: VehicleRow[];
    can: { manage: boolean; view_vehicles: boolean };
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Vehículos', href: route('vehicles.index') },
    { title: 'Partes', href: route('parts.index') },
    { title: props.part.name, href: route('parts.show', { part: props.part.id }) },
]);

const withPart = computed(() => props.vehicles.filter((vehicle) => vehicle.assignment !== null));
const average = computed(() =>
    withPart.value.length === 0
        ? null
        : Math.round(withPart.value.reduce((sum, vehicle) => sum + vehicle.assignment!.maintenance_interval_km, 0) / withPart.value.length),
);

const search = ref('');
const filter = ref<'all' | 'with' | 'without'>('all');
const visibleVehicles = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.vehicles.filter(
        (vehicle) =>
            (filter.value === 'all' || (filter.value === 'with') === (vehicle.assignment !== null)) &&
            (term === '' || `${vehicle.plate} ${vehicle.description}`.toLowerCase().includes(term)),
    );
});

/** Kilometraje escrito en cada fila (para agregar o cambiar). */
const kilometers = reactive<Record<number, number | ''>>(
    Object.fromEntries(props.vehicles.map((vehicle) => [vehicle.id, vehicle.assignment?.maintenance_interval_km ?? average.value ?? ''])),
);

const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);
const options = { preserveScroll: true, preserveState: true };

const assign = (vehicle: VehicleRow) => {
    router.post(
        route('parts.vehicles.assign', { part: props.part.id }),
        { vehicle_id: vehicle.id, maintenance_interval_km: kilometers[vehicle.id] },
        options,
    );
};

const update = (vehicle: VehicleRow) => {
    router.put(
        route('parts.vehicles.update', { part: props.part.id, vehiclePart: vehicle.assignment!.id }),
        { maintenance_interval_km: kilometers[vehicle.id] },
        options,
    );
};

const { confirm } = useConfirm();

const unassign = async (vehicle: VehicleRow) => {
    if (await confirm({ title: `¿Quitar "${props.part.name}" de ${vehicle.plate}?`, confirmText: 'Quitar', destructive: true })) {
        router.delete(route('parts.vehicles.unassign', { part: props.part.id, vehiclePart: vehicle.assignment!.id }), options);
    }
};

const massForm = useForm({ maintenance_interval_km: (average.value ?? '') as number | '' });
const missing = computed(() => props.vehicles.length - withPart.value.length);

const assignAll = async () => {
    const confirmed = await confirm({
        title: `¿Agregar "${props.part.name}" a ${missing.value} vehículo(s)?`,
        text: `Se agrega a todos los vehículos activos que no la tienen, con ${massForm.maintenance_interval_km || '—'} km entre mantenimientos.`,
        confirmText: 'Agregar',
    });

    if (confirmed) {
        massForm.post(route('parts.vehicles.assign-all', { part: props.part.id }), options);
    }
};

const unassignAll = async () => {
    const confirmed = await confirm({
        title: `¿Quitar "${props.part.name}" de todos los vehículos?`,
        text: 'Se quita también de los vehículos inactivos. La parte sigue en el catálogo.',
        confirmText: 'Quitar de todos',
        destructive: true,
    });

    if (confirmed) {
        router.delete(route('parts.vehicles.unassign-all', { part: props.part.id }), options);
    }
};

const km = (value: number) => `${new Intl.NumberFormat('es-CO').format(value)} km`;
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Parte: ${part.name}`" />

        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div class="space-y-1">
                <h2 class="break-words text-xl font-semibold tracking-tight">{{ part.name }}</h2>
                <p class="text-sm text-muted-foreground">
                    En {{ withPart.length }} de {{ vehicles.length }} vehículos activos<template v-if="average !== null">
                        · promedio {{ km(average) }} entre mantenimientos</template
                    >.
                </p>
            </div>

            <section v-if="can.manage" class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-4">
                <HeadingSmall title="Acciones masivas" />
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start">
                        <div class="grid gap-1">
                            <Input
                                v-model="massForm.maintenance_interval_km"
                                type="number"
                                min="1"
                                step="1"
                                class="sm:w-44"
                                placeholder="Kilometraje"
                                aria-label="Kilometraje entre mantenimientos para todos"
                            />
                            <InputError :message="massForm.errors.maintenance_interval_km" />
                        </div>
                        <Button type="button" :disabled="missing === 0 || massForm.processing" @click="assignAll">
                            <Plus />
                            Agregar a los que no la tienen ({{ missing }})
                        </Button>
                    </div>
                    <Button type="button" variant="outline" class="text-red-700 dark:text-red-300 lg:ml-auto" @click="unassignAll">
                        <Trash2 />
                        Quitar de todos
                    </Button>
                </div>
            </section>

            <section class="flex flex-col gap-3">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <HeadingSmall title="Vehículos activos" />
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <select
                            v-model="filter"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm"
                            aria-label="Mostrar vehículos"
                        >
                            <option value="all">Todos</option>
                            <option value="with">Con la parte</option>
                            <option value="without">Sin la parte</option>
                        </select>
                        <div class="relative sm:w-56">
                            <Search class="pointer-events-none absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                            <Input v-model="search" class="pl-8" placeholder="Buscar placa" aria-label="Buscar vehículo" />
                        </div>
                    </div>
                </div>

                <InputError :message="errors.maintenance_interval_km ?? errors.vehicle_id" />

                <ul class="divide-y divide-sidebar-border/70 rounded-xl border border-sidebar-border/70">
                    <li v-for="vehicle in visibleVehicles" :key="vehicle.id" class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center">
                        <div class="flex min-w-0 flex-1 items-center gap-3">
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full"
                                :class="
                                    vehicle.assignment
                                        ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300'
                                        : 'bg-muted text-muted-foreground'
                                "
                                :title="vehicle.assignment ? 'Tiene la parte' : 'No tiene la parte'"
                            >
                                <Check v-if="vehicle.assignment" class="size-3.5" />
                                <Minus v-else class="size-3.5" />
                            </span>
                            <div class="min-w-0">
                                <Link
                                    v-if="can.view_vehicles"
                                    :href="route('vehicles.show', { vehicle: vehicle.id })"
                                    class="font-medium tabular-nums hover:underline"
                                    >{{ vehicle.plate }}</Link
                                >
                                <span v-else class="font-medium tabular-nums">{{ vehicle.plate }}</span>
                                <span class="block truncate text-xs text-muted-foreground">{{ vehicle.description }}</span>
                            </div>
                        </div>

                        <template v-if="can.manage">
                            <div class="flex items-center gap-2">
                                <Input
                                    v-model="kilometers[vehicle.id]"
                                    type="number"
                                    min="1"
                                    step="1"
                                    class="h-8 w-32"
                                    :aria-label="`Kilometraje de ${part.name} en ${vehicle.plate}`"
                                />
                                <span class="text-xs text-muted-foreground">km</span>
                                <template v-if="vehicle.assignment">
                                    <button
                                        type="button"
                                        class="inline-flex h-8 items-center rounded-md bg-blue-50 px-3 text-xs font-medium text-blue-700 hover:bg-blue-100 disabled:opacity-50 dark:bg-blue-950 dark:text-blue-300 dark:hover:bg-blue-900"
                                        :disabled="kilometers[vehicle.id] === vehicle.assignment.maintenance_interval_km"
                                        @click="update(vehicle)"
                                    >
                                        Guardar
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-red-50 text-red-700 hover:bg-red-100 dark:bg-red-950 dark:text-red-300 dark:hover:bg-red-900"
                                        :aria-label="`Quitar ${part.name} de ${vehicle.plate}`"
                                        :title="`Quitar de ${vehicle.plate}`"
                                        @click="unassign(vehicle)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </template>
                                <button
                                    v-else
                                    type="button"
                                    class="inline-flex h-8 items-center gap-1 rounded-md bg-emerald-50 px-3 text-xs font-medium text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950 dark:text-emerald-300 dark:hover:bg-emerald-900"
                                    @click="assign(vehicle)"
                                >
                                    <Plus class="size-4" />
                                    Agregar
                                </button>
                            </div>
                        </template>
                        <span v-else-if="vehicle.assignment" class="whitespace-nowrap text-sm tabular-nums">{{
                            km(vehicle.assignment.maintenance_interval_km)
                        }}</span>
                    </li>
                    <li v-if="visibleVehicles.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">
                        No hay vehículos que coincidan.
                    </li>
                </ul>
            </section>

            <div>
                <Link :href="route('parts.index')" class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="size-4" />
                    Regresar a partes
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
