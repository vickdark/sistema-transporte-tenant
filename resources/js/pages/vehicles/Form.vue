<script setup lang="ts">
import FormSection from '@/components/FormSection.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import VehicleDocumentFields, {
    type CurrentDocument,
    type DocumentFormValue,
    type DocumentTypeInfo,
} from '@/components/vehicles/VehicleDocumentFields.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SearchSelectOption, type SelectOption } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Building2, CarFront, CircleAlert, FileText, Handshake, LoaderCircle, ScrollText, Truck } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

type OwnerType = 'company' | 'supplier' | 'ally';

interface VehicleData {
    id: number;
    plate: string;
    internal_number: string | null;
    current_mileage: number;
    registration_date: string;
    registration_place: string;
    model_year: number;
    brand: string;
    line: string;
    vehicle_class: string;
    engine_number: string;
    chassis_number: string;
    color: string;
    displacement: number | null;
    fuel_type: string | null;
    owner_type: OwnerType;
    owner: SearchSelectOption | null;
    /** Documento vigente de cada tipo (un arreglo vacío si no tiene ninguno). */
    documents: Record<string, CurrentDocument> | [];
}

const props = defineProps<{
    item: VehicleData | null;
    classes: SelectOption[];
    fuelTypes: SelectOption[];
    /** Tercero del usuario: si pertenece a uno, el vehículo queda a su nombre y no se elige dueño. */
    fixedOwner: string | null;
    companyName: string | null;
    documentTypes: DocumentTypeInfo[];
    maxDocumentMb: number;
}>();

const isEditing = computed(() => props.item !== null);
const title = computed(() => (isEditing.value ? `Editar vehículo ${props.item!.plate}` : 'Crear vehículo'));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Vehículos', href: route('vehicles.index') },
    ...(isEditing.value ? [{ title: props.item!.plate, href: route('vehicles.show', { vehicle: props.item!.id }) }] : []),
    {
        title: isEditing.value ? 'Editar' : 'Crear vehículo',
        href: isEditing.value ? route('vehicles.edit', { vehicle: props.item!.id }) : route('vehicles.create'),
    },
]);

function currentDocument(type: string): CurrentDocument | null {
    const documents = props.item?.documents;

    return documents && !Array.isArray(documents) ? (documents[type] ?? null) : null;
}

const form = useForm({
    plate: props.item?.plate ?? '',
    internal_number: props.item?.internal_number ?? '',
    current_mileage: props.item?.current_mileage ?? ('' as number | ''),
    registration_date: props.item?.registration_date ?? '',
    registration_place: props.item?.registration_place ?? '',
    model_year: props.item?.model_year ?? ('' as number | ''),
    brand: props.item?.brand ?? '',
    line: props.item?.line ?? '',
    vehicle_class: props.item?.vehicle_class ?? '',
    engine_number: props.item?.engine_number ?? '',
    chassis_number: props.item?.chassis_number ?? '',
    color: props.item?.color ?? '',
    displacement: props.item?.displacement ?? ('' as number | ''),
    fuel_type: props.item?.fuel_type ?? '',
    owner_type: (props.item?.owner_type ?? 'company') as OwnerType,
    owner_id: (props.item?.owner?.value ?? null) as string | number | null,
    documents: Object.fromEntries(
        props.documentTypes.map((document) => {
            const current = currentDocument(document.type);

            return [
                document.type,
                { number: current?.number ?? '', issued_on: current?.issued_on ?? '', expires_on: current?.expires_on ?? '', file: null },
            ];
        }),
    ) as Record<string, DocumentFormValue>,
});

const errors = computed(() => form.errors as Record<string, string | undefined>);

/** Errores de un documento (vienen como documents.<tipo>.<campo>). */
const documentErrors = (type: string) => ({
    number: errors.value[`documents.${type}.number`],
    issued_on: errors.value[`documents.${type}.issued_on`],
    expires_on: errors.value[`documents.${type}.expires_on`],
    file: errors.value[`documents.${type}.file`],
});

const owner = ref<SearchSelectOption | null>(props.item?.owner ?? null);
watch(owner, (selected) => (form.owner_id = selected?.value ?? null));
watch(
    () => form.owner_type,
    () => (owner.value = null),
);

const ownerTypes = computed(() => [
    { value: 'company' as const, label: props.companyName ?? 'La empresa', description: 'Vehículo propio de la empresa.', icon: Building2 },
    { value: 'supplier' as const, label: 'Proveedor', description: 'Lo aporta un proveedor.', icon: Truck },
    { value: 'ally' as const, label: 'Aliado', description: 'Lo opera un aliado con sus servicios.', icon: Handshake },
]);

/** Documentos agrupados (tarjetas, revisiones, seguros) en el orden en que llegan. */
const documentGroups = computed(() => {
    const groups = new Map<string, DocumentTypeInfo[]>();
    props.documentTypes.forEach((document) => groups.set(document.group, [...(groups.get(document.group) ?? []), document]));

    return [...groups.entries()].map(([name, documents]) => ({ name, documents }));
});

const registeredDocuments = computed(
    () =>
        props.documentTypes.filter((document) => {
            const value = form.documents[document.type];

            return currentDocument(document.type) !== null || Boolean(value.number || value.issued_on || value.expires_on || value.file);
        }).length,
);

/** Secciones del formulario con los campos de cada una (para el índice y para marcar errores). */
const sections = computed(() => [
    {
        id: 'vehicle',
        title: 'Vehículo',
        fields: ['plate', 'internal_number', 'vehicle_class', 'brand', 'line', 'model_year', 'color', 'fuel_type', 'displacement'],
    },
    {
        id: 'registration',
        title: 'Matrícula y registro',
        fields: ['registration_date', 'registration_place', 'engine_number', 'chassis_number', 'current_mileage'],
    },
    { id: 'owner', title: 'Propietario', fields: ['owner_type', 'owner_id'] },
    { id: 'documents', title: 'Documentos', fields: Object.keys(errors.value).filter((key) => key.startsWith('documents')) },
]);

const errorCount = (sectionId: string) => {
    const section = sections.value.find((current) => current.id === sectionId);

    return section ? section.fields.filter((field) => errors.value[field]).length : 0;
};

const totalErrors = computed(() => Object.values(errors.value).filter(Boolean).length);
const firstSectionWithErrors = computed(() => sections.value.find((section) => errorCount(section.id) > 0)?.id ?? null);

const goTo = (sectionId: string) => document.getElementById(sectionId)?.scrollIntoView({ behavior: 'smooth', block: 'start' });

const submit = () => {
    const options = {
        preserveScroll: true,
        onError: () => nextTick(() => firstSectionWithErrors.value && goTo(firstSectionWithErrors.value)),
    };

    if (isEditing.value) {
        // Con archivos el envío es multipart: va por POST simulando el PUT.
        form.transform((data) => ({ ...data, _method: 'put' })).post(route('vehicles.update', { vehicle: props.item!.id }), options);
    } else {
        form.post(route('vehicles.store'), options);
    }
};

const maxYear = new Date().getFullYear() + 1;
const today = new Date().toISOString().slice(0, 10);
const selectClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="title" />

        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <Heading
                :title="title"
                :description="
                    isEditing
                        ? 'Corrige los datos y documentos del vehículo. Para renovar un documento (y guardar el anterior en el historial) y para las fotos, usa su ficha.'
                        : 'Registra el vehículo con sus documentos. Después de guardarlo podrás agregar sus fotos.'
                "
            />

            <div class="grid gap-6 xl:grid-cols-[13rem_minmax(0,1fr)]">
                <!-- Índice de secciones (escritorio) -->
                <nav class="hidden xl:block" aria-label="Secciones del formulario">
                    <ul class="sticky top-6 flex flex-col gap-1 text-sm">
                        <li v-for="section in sections" :key="section.id">
                            <a
                                :href="`#${section.id}`"
                                class="flex items-center justify-between gap-2 rounded-md px-3 py-2 hover:bg-muted"
                                @click.prevent="goTo(section.id)"
                            >
                                <span>{{ section.title }}</span>
                                <span
                                    v-if="errorCount(section.id) > 0"
                                    class="rounded-full bg-red-100 px-2 text-xs font-medium text-red-800 dark:bg-red-950 dark:text-red-200"
                                    >{{ errorCount(section.id) }}</span
                                >
                                <span v-else-if="section.id === 'documents'" class="text-xs tabular-nums text-muted-foreground"
                                    >{{ registeredDocuments }}/{{ documentTypes.length }}</span
                                >
                            </a>
                        </li>
                    </ul>
                </nav>

                <form class="flex min-w-0 max-w-4xl flex-col gap-6" novalidate @submit.prevent="submit">
                    <FormSection
                        id="vehicle"
                        title="Vehículo"
                        description="Cómo se identifica el vehículo en la flota."
                        :icon="CarFront"
                        :has-errors="errorCount('vehicle') > 0"
                    >
                        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            <div class="grid gap-2">
                                <Label for="plate">Placa *</Label>
                                <Input
                                    id="plate"
                                    v-model="form.plate"
                                    class="font-medium uppercase tracking-wider"
                                    maxlength="6"
                                    placeholder="ABC123"
                                    autocomplete="off"
                                    @input="form.plate = form.plate.toUpperCase()"
                                />
                                <InputError :message="form.errors.plate" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="internal_number">Número interno</Label>
                                <Input id="internal_number" v-model="form.internal_number" maxlength="50" />
                                <InputError :message="form.errors.internal_number" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="vehicle_class">Clase de vehículo *</Label>
                                <select id="vehicle_class" v-model="form.vehicle_class" :class="selectClass">
                                    <option value="" disabled>Seleccione…</option>
                                    <option v-for="vehicleClass in classes" :key="vehicleClass.value" :value="vehicleClass.value">
                                        {{ vehicleClass.label }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.vehicle_class" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="brand">Marca *</Label>
                                <Input id="brand" v-model="form.brand" maxlength="50" placeholder="Ej: TOYOTA" />
                                <InputError :message="form.errors.brand" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="line">Línea *</Label>
                                <Input id="line" v-model="form.line" maxlength="50" placeholder="Ej: HIACE" />
                                <InputError :message="form.errors.line" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="model_year">Modelo (año) *</Label>
                                <Input id="model_year" v-model="form.model_year" type="number" min="1950" :max="maxYear" />
                                <InputError :message="form.errors.model_year" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="color">Color *</Label>
                                <Input id="color" v-model="form.color" maxlength="50" />
                                <InputError :message="form.errors.color" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="fuel_type">Combustible</Label>
                                <select id="fuel_type" v-model="form.fuel_type" :class="selectClass">
                                    <option value="">Sin especificar</option>
                                    <option v-for="fuel in fuelTypes" :key="fuel.value" :value="fuel.value">{{ fuel.label }}</option>
                                </select>
                                <InputError :message="form.errors.fuel_type" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="displacement">Cilindraje (cc)</Label>
                                <Input id="displacement" v-model="form.displacement" type="number" min="1" step="1" placeholder="Ej: 2700" />
                                <InputError :message="form.errors.displacement" />
                            </div>
                        </div>
                    </FormSection>

                    <FormSection
                        id="registration"
                        title="Matrícula y registro"
                        description="Datos de la tarjeta de propiedad y kilometraje con el que entra al sistema."
                        :icon="ScrollText"
                        :has-errors="errorCount('registration') > 0"
                    >
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="registration_date">Fecha de matrícula *</Label>
                                <Input id="registration_date" v-model="form.registration_date" type="date" :max="today" />
                                <InputError :message="form.errors.registration_date" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="registration_place">Lugar de matrícula *</Label>
                                <Input id="registration_place" v-model="form.registration_place" maxlength="100" placeholder="Ej: Cartagena" />
                                <InputError :message="form.errors.registration_place" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="engine_number">Número de motor *</Label>
                                <Input id="engine_number" v-model="form.engine_number" maxlength="50" class="uppercase" />
                                <InputError :message="form.errors.engine_number" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="chassis_number">Número de chasis *</Label>
                                <Input id="chassis_number" v-model="form.chassis_number" maxlength="50" class="uppercase" />
                                <InputError :message="form.errors.chassis_number" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="current_mileage">Kilometraje actual *</Label>
                                <div class="relative">
                                    <Input id="current_mileage" v-model="form.current_mileage" type="number" min="0" step="0.01" class="pr-10" />
                                    <span class="pointer-events-none absolute right-3 top-2 text-sm text-muted-foreground">km</span>
                                </div>
                                <InputError :message="form.errors.current_mileage" />
                            </div>
                        </div>
                    </FormSection>

                    <FormSection
                        id="owner"
                        title="Propietario"
                        description="A nombre de quién está el vehículo."
                        :icon="Building2"
                        :has-errors="errorCount('owner') > 0"
                    >
                        <p v-if="fixedOwner" class="text-sm text-muted-foreground">
                            El vehículo queda a nombre de <span class="font-medium text-foreground">{{ fixedOwner }}</span
                            >.
                        </p>
                        <div v-else class="flex flex-col gap-4">
                            <div class="grid gap-3 sm:grid-cols-3" role="radiogroup" aria-label="Propietario">
                                <label
                                    v-for="type in ownerTypes"
                                    :key="type.value"
                                    class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors"
                                    :class="
                                        form.owner_type === type.value ? 'border-primary bg-primary/5' : 'border-sidebar-border/70 hover:bg-muted/50'
                                    "
                                >
                                    <input v-model="form.owner_type" type="radio" name="owner_type" :value="type.value" class="sr-only" />
                                    <component
                                        :is="type.icon"
                                        class="mt-0.5 size-4 shrink-0"
                                        :class="form.owner_type === type.value ? 'text-primary' : 'text-muted-foreground'"
                                    />
                                    <span class="min-w-0">
                                        <span class="block break-words text-sm font-medium">{{ type.label }}</span>
                                        <span class="block text-xs text-muted-foreground">{{ type.description }}</span>
                                    </span>
                                </label>
                            </div>
                            <InputError :message="form.errors.owner_type" />

                            <div v-if="form.owner_type !== 'company'" class="grid max-w-xl gap-2">
                                <Label for="owner_search">{{ form.owner_type === 'ally' ? 'Aliado' : 'Proveedor' }} *</Label>
                                <SearchSelect
                                    id="owner_search"
                                    :key="form.owner_type"
                                    v-model="owner"
                                    :search-url="route('vehicles.search-owners', { type: form.owner_type })"
                                    :placeholder="form.owner_type === 'ally' ? 'Buscar aliado por nombre o NIT' : 'Buscar proveedor por nombre o NIT'"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Si es un tercero mixto, el vehículo queda a nombre de su proveedor y su aliado.
                                </p>
                                <InputError :message="form.errors.owner_id" />
                            </div>
                        </div>
                    </FormSection>

                    <FormSection
                        id="documents"
                        title="Documentos"
                        :description="`Todos son opcionales: los que dejes vacíos no se tocan. ${registeredDocuments} de ${documentTypes.length} registrados.`"
                        :icon="FileText"
                        :has-errors="errorCount('documents') > 0"
                    >
                        <div class="flex flex-col gap-5">
                            <div v-for="group in documentGroups" :key="group.name" class="flex flex-col gap-2">
                                <h4 class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ group.name }}</h4>
                                <VehicleDocumentFields
                                    v-for="document in group.documents"
                                    :key="document.type"
                                    v-model="form.documents[document.type]"
                                    :document="document"
                                    :current="currentDocument(document.type)"
                                    :errors="documentErrors(document.type)"
                                    :max-file-mb="maxDocumentMb"
                                />
                            </div>
                        </div>
                    </FormSection>

                    <!-- Acciones siempre visibles -->
                    <div
                        class="sticky bottom-0 z-10 -mx-4 flex flex-col gap-3 border-t border-sidebar-border/70 bg-background/95 px-4 py-3 backdrop-blur sm:mx-0 sm:flex-row sm:items-center sm:rounded-xl sm:border"
                    >
                        <button
                            v-if="totalErrors > 0"
                            type="button"
                            class="flex items-center gap-1.5 text-sm font-medium text-red-600 hover:underline dark:text-red-400"
                            @click="firstSectionWithErrors && goTo(firstSectionWithErrors)"
                        >
                            <CircleAlert class="size-4" />
                            {{ totalErrors === 1 ? 'Hay 1 campo por corregir' : `Hay ${totalErrors} campos por corregir` }}
                        </button>
                        <span v-else class="text-sm text-muted-foreground">Los campos con * son obligatorios.</span>
                        <div class="flex flex-col-reverse gap-2 sm:ml-auto sm:flex-row">
                            <Button variant="outline" as-child class="w-full sm:w-auto">
                                <Link :href="isEditing ? route('vehicles.show', { vehicle: item!.id }) : route('vehicles.index')">Cancelar</Link>
                            </Button>
                            <Button type="submit" :disabled="form.processing" class="w-full sm:w-auto">
                                <LoaderCircle v-if="form.processing" class="animate-spin" />
                                {{ isEditing ? 'Guardar cambios' : 'Crear vehículo' }}
                            </Button>
                        </div>
                    </div>
                    <div v-if="form.progress" class="h-1.5 overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full bg-primary transition-all" :style="{ width: `${form.progress.percentage}%` }" />
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
