<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import ImageUploadField from '@/components/ImageUploadField.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

interface Profile {
    address: string | null;
    phone: string | null;
    email: string | null;
    service_type: string;
    territorial_code: string | null;
    habilitation_resolution: string | null;
    habilitation_year: string | null;
    logo_url: string | null;
    signature_url: string | null;
}

const props = defineProps<{
    identity: { name: string; nit: string | null };
    profile: Profile;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Configuración de la empresa', href: '/company' }];

const form = useForm({
    address: props.profile.address ?? '',
    phone: props.profile.phone ?? '',
    email: props.profile.email ?? '',
    service_type: props.profile.service_type,
    territorial_code: props.profile.territorial_code ?? '',
    habilitation_resolution: props.profile.habilitation_resolution ?? '',
    habilitation_year: props.profile.habilitation_year ?? '',
});

const submit = () => {
    form.put(route('company.update'), { preserveScroll: true });
};

/** Vista previa del número del FUEC: prefijo de la empresa + año, contrato y consecutivo. */
const fuecPreview = computed(() =>
    [
        form.territorial_code || '___',
        form.habilitation_resolution ? form.habilitation_resolution.padStart(4, '0') : '____',
        form.habilitation_year || '__',
        new Date().getFullYear(),
        '####',
        '####',
    ].join(' '),
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Configuración de la empresa" />

        <div class="flex flex-1 flex-col gap-8 p-4 md:p-6">
            <Heading title="Configuración de la empresa" description="Datos que aparecen en los documentos de la empresa, como el FUEC." />

            <section class="flex max-w-4xl flex-col gap-4">
                <HeadingSmall title="Identidad" description="La administra el panel de la plataforma; si hay un error, pídele que la corrija." />
                <dl class="grid grid-cols-1 gap-4 rounded-xl border border-sidebar-border/70 bg-muted/30 p-4 sm:grid-cols-2">
                    <div class="min-w-0">
                        <dt class="text-xs uppercase text-muted-foreground">Razón social</dt>
                        <dd class="mt-1 break-words text-sm font-medium">{{ identity.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-muted-foreground">NIT</dt>
                        <dd class="mt-1 text-sm font-medium tabular-nums">{{ identity.nit ?? '—' }}</dd>
                    </div>
                </dl>
            </section>

            <form class="flex max-w-4xl flex-col gap-8" @submit.prevent="submit">
                <section class="flex flex-col gap-4">
                    <HeadingSmall title="Contacto en los documentos" />
                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="grid gap-2 md:col-span-2">
                            <Label for="address">Dirección</Label>
                            <Input id="address" v-model="form.address" required placeholder="Calle 10 # 20-30, Cartagena" />
                            <InputError :message="form.errors.address" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="phone">Teléfono</Label>
                            <Input id="phone" v-model="form.phone" required inputmode="tel" />
                            <InputError :message="form.errors.phone" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="email">Correo</Label>
                            <Input id="email" v-model="form.email" type="email" required />
                            <InputError :message="form.errors.email" />
                        </div>
                        <div class="grid gap-2 md:col-span-2">
                            <Label for="service_type">Tipo de servicio</Label>
                            <Input id="service_type" v-model="form.service_type" required />
                            <InputError :message="form.errors.service_type" />
                        </div>
                    </div>
                </section>

                <section class="flex flex-col gap-4">
                    <HeadingSmall
                        title="Habilitación (FUEC)"
                        description="Datos de la resolución que habilita a la empresa. Forman el inicio del número de cada FUEC."
                    />
                    <div class="grid gap-6 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="territorial_code">Dirección territorial</Label>
                            <Input
                                id="territorial_code"
                                v-model="form.territorial_code"
                                required
                                inputmode="numeric"
                                maxlength="3"
                                placeholder="213"
                            />
                            <InputError :message="form.errors.territorial_code" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="habilitation_resolution">Resolución</Label>
                            <Input
                                id="habilitation_resolution"
                                v-model="form.habilitation_resolution"
                                required
                                inputmode="numeric"
                                maxlength="4"
                                placeholder="0114"
                            />
                            <InputError :message="form.errors.habilitation_resolution" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="habilitation_year">Año de habilitación</Label>
                            <Input
                                id="habilitation_year"
                                v-model="form.habilitation_year"
                                required
                                inputmode="numeric"
                                maxlength="2"
                                placeholder="16"
                            />
                            <InputError :message="form.errors.habilitation_year" />
                        </div>
                    </div>
                    <div class="rounded-lg border border-dashed border-sidebar-border p-3 text-sm">
                        <span class="text-muted-foreground">Así empezará el número del FUEC:</span>
                        <span class="ml-1 break-all font-mono font-medium">{{ fuecPreview }}</span>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Territorial · resolución · año de habilitación · año de expedición · contrato · consecutivo.
                        </p>
                    </div>
                </section>

                <div>
                    <Button type="submit" :disabled="form.processing" class="w-full sm:w-auto">
                        <LoaderCircle v-if="form.processing" class="animate-spin" />
                        Guardar
                    </Button>
                </div>
            </form>

            <section class="flex max-w-4xl flex-col gap-4">
                <HeadingSmall title="Imágenes de los documentos" description="Elige la imagen, revísala junto a la actual y guárdala." />
                <div class="grid gap-4 md:grid-cols-2">
                    <ImageUploadField
                        title="Logo"
                        description="Aparece en el encabezado del FUEC y de los documentos."
                        :current-url="profile.logo_url"
                        :upload-url="route('company.files.store', { type: 'logo' })"
                        :delete-url="route('company.files.destroy', { type: 'logo' })"
                    />
                    <ImageUploadField
                        title="Firma digital"
                        description="Va al pie del FUEC. Privada: solo la ve quien administra la configuración. Mejor en PNG con fondo transparente."
                        :current-url="profile.signature_url"
                        :upload-url="route('company.files.store', { type: 'signature' })"
                        :delete-url="route('company.files.destroy', { type: 'signature' })"
                        transparent
                    />
                </div>
            </section>
        </div>
    </AppLayout>
</template>
