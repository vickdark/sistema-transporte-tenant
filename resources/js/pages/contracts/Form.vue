<script setup lang="ts">
import ActiveAllyBanner from '@/components/contracts/ActiveAllyBanner.vue';
import Heading from '@/components/Heading.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SelectOption } from '@/types';
import { type ContractData, type ContractModule, clientUrl, formatDate } from '@/types/contracts';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Search, TriangleAlert, X } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';

interface ClientOption {
    id: number;
    name: string;
    identification: string;
    phone: string;
    email: string | null;
    /** Persona: ella misma es la responsable del contrato; empresa: se piden los datos. */
    is_company: boolean;
    current_contract?: { number: string; ends_on: string } | null;
}

const props = defineProps<{
    module: ContractModule;
    contract: ContractData | null;
    client: ClientOption | null;
    purposes: SelectOption[];
}>();

const title = computed(() => (props.contract ? `Editar contrato ${props.contract.number}` : 'Nuevo contrato'));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: props.module.title, href: props.module.urls.index },
    { title: title.value, href: props.module.urls.create },
]);

const form = useForm({
    client_id: props.client?.id ?? null,
    purpose: props.contract?.purpose ?? '',
    starts_on: props.contract?.starts_on ?? '',
    ends_on: props.contract?.ends_on ?? '',
    responsible_identification: props.contract?.responsible_identification ?? '',
    responsible_name: props.contract?.responsible_name ?? '',
    responsible_email: props.contract?.responsible_email ?? '',
    responsible_phone: props.contract?.responsible_phone ?? '',
});

// Buscador de clientes (solo al crear): clientes activos del mismo dueño.
const selectedClient = ref<ClientOption | null>(props.client);
const search = ref('');
const results = ref<ClientOption[]>([]);
const searching = ref(false);
let timer: number | undefined;

const runSearch = async (term: string) => {
    searching.value = true;

    try {
        const response = await fetch(`${props.module.urls.search_clients}?search=${encodeURIComponent(term)}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        results.value = response.ok ? await response.json() : [];
    } finally {
        searching.value = false;
    }
};

watch(search, (term) => {
    window.clearTimeout(timer);
    timer = window.setTimeout(() => runSearch(term), 300);
});
onUnmounted(() => window.clearTimeout(timer));

const chooseClient = (client: ClientOption) => {
    selectedClient.value = client;
    form.client_id = client.id;
    results.value = [];
    search.value = '';
};

const clearClient = () => {
    selectedClient.value = null;
    form.client_id = null;
};

/** El fin no puede pasar del 31 de diciembre del año de inicio (regla del legacy). */
const maxEndDate = computed(() => (form.starts_on ? `${form.starts_on.slice(0, 4)}-12-31` : undefined));

const submit = () => {
    if (props.contract) {
        form.put(props.contract.update_url);
    } else {
        form.post(props.module.urls.store);
    }
};

const cancelUrl = computed(() => (props.client ? clientUrl(props.module, props.client.id) : props.module.urls.index));
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="title" />

        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <Heading :title="title" description="Todos los campos son obligatorios." />
            <ActiveAllyBanner :module="module" />

            <form class="flex max-w-4xl flex-col gap-8" @submit.prevent="submit">
                <section class="flex flex-col gap-4">
                    <HeadingSmall title="Cliente" />

                    <div v-if="selectedClient" class="flex items-start justify-between gap-3 rounded-lg border border-sidebar-border/70 p-3">
                        <div class="min-w-0">
                            <p class="break-words font-medium">{{ selectedClient.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ selectedClient.identification }}</p>
                            <p
                                v-if="selectedClient.current_contract && !contract"
                                class="mt-2 flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-300"
                            >
                                <TriangleAlert class="size-3.5 shrink-0" />
                                Tiene vigente el contrato {{ selectedClient.current_contract.number }} hasta el
                                {{ formatDate(selectedClient.current_contract.ends_on) }}: el nuevo debe empezar después.
                            </p>
                        </div>
                        <button
                            v-if="!contract"
                            type="button"
                            class="rounded-md p-1 text-muted-foreground hover:bg-muted"
                            aria-label="Cambiar cliente"
                            @click="clearClient"
                        >
                            <X class="size-4" />
                        </button>
                    </div>

                    <div v-else class="relative">
                        <Search class="pointer-events-none absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                        <Input
                            v-model="search"
                            type="search"
                            class="pl-8"
                            placeholder="Buscar cliente por nombre o identificación"
                            aria-label="Buscar cliente"
                            @focus="runSearch(search)"
                        />
                        <LoaderCircle v-if="searching" class="absolute right-2.5 top-2.5 size-4 animate-spin text-muted-foreground" />
                        <ul
                            v-if="results.length"
                            class="absolute z-10 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-sidebar-border bg-background shadow-lg"
                        >
                            <li v-for="option in results" :key="option.id">
                                <button
                                    type="button"
                                    class="flex w-full flex-col items-start px-3 py-2 text-left text-sm hover:bg-muted"
                                    @click="chooseClient(option)"
                                >
                                    <span class="font-medium">{{ option.name }}</span>
                                    <span class="text-xs text-muted-foreground">
                                        {{ option.identification }}
                                        <template v-if="option.current_contract"> · vigente {{ option.current_contract.number }}</template>
                                    </span>
                                </button>
                            </li>
                        </ul>
                    </div>
                    <InputError :message="form.errors.client_id" />
                </section>

                <section class="flex flex-col gap-4">
                    <HeadingSmall title="Contrato" />
                    <div class="grid gap-6 md:grid-cols-3">
                        <div class="grid gap-2 md:col-span-3">
                            <Label for="purpose">Objeto del contrato</Label>
                            <select
                                id="purpose"
                                v-model="form.purpose"
                                class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm"
                                required
                            >
                                <option value="" disabled>— Seleccione el objeto del contrato —</option>
                                <option v-for="purpose in purposes" :key="purpose.value" :value="purpose.value">{{ purpose.label }}</option>
                            </select>
                            <InputError :message="form.errors.purpose" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="starts_on">Fecha de inicio</Label>
                            <Input id="starts_on" v-model="form.starts_on" type="date" required />
                            <InputError :message="form.errors.starts_on" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="ends_on">Fecha de fin</Label>
                            <Input id="ends_on" v-model="form.ends_on" type="date" :min="form.starts_on || undefined" :max="maxEndDate" required />
                            <InputError :message="form.errors.ends_on" />
                        </div>
                        <p class="self-end pb-2 text-xs text-muted-foreground">El fin no puede pasar del 31 de diciembre del año de inicio.</p>
                    </div>
                </section>

                <section v-if="selectedClient" class="flex flex-col gap-4">
                    <template v-if="selectedClient.is_company">
                        <HeadingSmall
                            title="Responsable"
                            description="El cliente es una empresa: indica la persona responsable del contrato (requerido por normativa)."
                        />
                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="responsible_identification">Identificación</Label>
                                <Input id="responsible_identification" v-model="form.responsible_identification" required maxlength="20" />
                                <InputError :message="form.errors.responsible_identification" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="responsible_name">Nombre</Label>
                                <Input id="responsible_name" v-model="form.responsible_name" required maxlength="150" />
                                <InputError :message="form.errors.responsible_name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="responsible_email">Correo</Label>
                                <Input id="responsible_email" v-model="form.responsible_email" type="email" required maxlength="100" />
                                <InputError :message="form.errors.responsible_email" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="responsible_phone">Teléfono</Label>
                                <Input id="responsible_phone" v-model="form.responsible_phone" inputmode="tel" required maxlength="20" />
                                <InputError :message="form.errors.responsible_phone" />
                            </div>
                        </div>
                    </template>
                    <template v-else>
                        <HeadingSmall title="Responsable" />
                        <div class="rounded-lg border border-sidebar-border/70 bg-muted/30 p-3 text-sm">
                            <p class="text-muted-foreground">El cliente es una persona: ella misma es la responsable del contrato.</p>
                            <p class="mt-1 font-medium">
                                {{ selectedClient.name }} · {{ selectedClient.identification }} · {{ selectedClient.phone }}
                                <template v-if="selectedClient.email"> · {{ selectedClient.email }}</template>
                            </p>
                        </div>
                        <div v-if="!selectedClient.email" class="grid max-w-md gap-2">
                            <Label for="responsible_email">Correo del cliente</Label>
                            <Input id="responsible_email" v-model="form.responsible_email" type="email" required maxlength="100" />
                            <p class="text-xs text-muted-foreground">
                                El cliente no tiene correo registrado: se guardará en el contrato y en el cliente.
                            </p>
                            <InputError :message="form.errors.responsible_email" />
                        </div>
                    </template>
                </section>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                    <Button variant="outline" as-child class="w-full sm:w-auto">
                        <Link :href="cancelUrl">Cancelar</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing" class="w-full sm:w-auto">
                        <LoaderCircle v-if="form.processing" class="animate-spin" />
                        Guardar contrato
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
