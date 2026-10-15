<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SelectOption } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed, watch } from 'vue';

interface ClientData {
    id: number;
    type: 'person' | 'company';
    identification_type: 'cc' | 'nit';
    identification: string;
    check_digit: string | null;
    branch_code: string | null;
    first_names: string | null;
    last_names: string | null;
    business_name: string | null;
    trade_name: string | null;
    city: string;
    address: string;
    phone: string;
    extension: string | null;
}

const props = defineProps<{
    client: ClientData | null;
    types: SelectOption[];
    identificationTypes: SelectOption[];
    suggestedIdentificationTypes: Record<string, string>;
}>();

const isEditing = computed(() => props.client !== null);
const title = computed(() => (isEditing.value ? 'Editar cliente' : 'Crear cliente'));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Clientes', href: route('clients.index') },
    { title: title.value, href: isEditing.value ? route('clients.edit', { client: props.client!.id }) : route('clients.create') },
]);

const form = useForm({
    type: props.client?.type ?? '',
    identification_type: props.client?.identification_type ?? '',
    identification: props.client?.identification ?? '',
    check_digit: props.client?.check_digit ?? '',
    branch_code: props.client?.branch_code ?? '',
    first_names: props.client?.first_names ?? '',
    last_names: props.client?.last_names ?? '',
    business_name: props.client?.business_name ?? '',
    trade_name: props.client?.trade_name ?? '',
    city: props.client?.city ?? '',
    address: props.client?.address ?? '',
    phone: props.client?.phone ?? '',
    extension: props.client?.extension ?? '',
});

// Como en el legacy: al elegir el tipo se sugiere el tipo de identificación (se puede cambiar).
watch(
    () => form.type,
    (type) => {
        if (!isEditing.value && type) {
            form.identification_type = props.suggestedIdentificationTypes[type] ?? '';
        }
    },
);

const isPerson = computed(() => form.type === 'person');
const isCompany = computed(() => form.type === 'company');

const submit = () => {
    if (isEditing.value) {
        form.put(route('clients.update', { client: props.client!.id }));
    } else {
        form.post(route('clients.store'));
    }
};

const selectClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm disabled:cursor-not-allowed disabled:opacity-60';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="title" />

        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <Heading :title="title" description="Los campos con * son obligatorios." />

            <form class="flex max-w-4xl flex-col gap-8" @submit.prevent="submit">
                <section class="flex flex-col gap-4">
                    <HeadingSmall title="Identificación" />
                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="type">Tipo de cliente *</Label>
                            <select id="type" v-model="form.type" :class="selectClass" :disabled="isEditing" required>
                                <option value="" disabled>Seleccione…</option>
                                <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
                            </select>
                            <p v-if="isEditing" class="text-xs text-muted-foreground">El tipo de cliente no se cambia después de crearlo.</p>
                            <InputError :message="form.errors.type" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="identification_type">Tipo de identificación *</Label>
                            <select id="identification_type" v-model="form.identification_type" :class="selectClass" required>
                                <option value="" disabled>Seleccione…</option>
                                <option v-for="type in identificationTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                            </select>
                            <InputError :message="form.errors.identification_type" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="identification">Identificación *</Label>
                            <Input id="identification" v-model="form.identification" inputmode="numeric" required maxlength="20" />
                            <InputError :message="form.errors.identification" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="check_digit">DV</Label>
                                <Input id="check_digit" v-model="form.check_digit" inputmode="numeric" maxlength="2" />
                                <InputError :message="form.errors.check_digit" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="branch_code">Código de sucursal</Label>
                                <Input id="branch_code" v-model="form.branch_code" maxlength="20" />
                                <InputError :message="form.errors.branch_code" />
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="isPerson" class="flex flex-col gap-4">
                    <HeadingSmall title="Persona" />
                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="first_names">Nombres *</Label>
                            <Input id="first_names" v-model="form.first_names" required />
                            <InputError :message="form.errors.first_names" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="last_names">Apellidos *</Label>
                            <Input id="last_names" v-model="form.last_names" required />
                            <InputError :message="form.errors.last_names" />
                        </div>
                    </div>
                </section>

                <section v-if="isCompany" class="flex flex-col gap-4">
                    <HeadingSmall title="Empresa" />
                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="business_name">Razón social *</Label>
                            <Input id="business_name" v-model="form.business_name" required />
                            <InputError :message="form.errors.business_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="trade_name">Nombre comercial</Label>
                            <Input id="trade_name" v-model="form.trade_name" />
                            <InputError :message="form.errors.trade_name" />
                        </div>
                    </div>
                </section>

                <section class="flex flex-col gap-4">
                    <HeadingSmall title="Contacto" />
                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="city">Ciudad *</Label>
                            <Input id="city" v-model="form.city" required />
                            <InputError :message="form.errors.city" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="address">Dirección *</Label>
                            <Input id="address" v-model="form.address" required />
                            <InputError :message="form.errors.address" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="phone">Teléfono *</Label>
                            <Input id="phone" v-model="form.phone" inputmode="tel" required />
                            <p class="text-xs text-muted-foreground">Solo números; los espacios y el +57 se quitan solos.</p>
                            <InputError :message="form.errors.phone" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="extension">Extensión</Label>
                            <Input id="extension" v-model="form.extension" maxlength="10" />
                            <InputError :message="form.errors.extension" />
                        </div>
                    </div>
                </section>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                    <Button variant="outline" as-child class="w-full sm:w-auto">
                        <Link :href="route('clients.index')">Cancelar</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing" class="w-full sm:w-auto">
                        <LoaderCircle v-if="form.processing" class="animate-spin" />
                        Guardar cliente
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
