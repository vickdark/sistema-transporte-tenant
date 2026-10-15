<script setup lang="ts">
import LegacyFilesFields from '@/components/central/LegacyFilesFields.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import CentralLayout from '@/layouts/CentralLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const props = defineProps<{
    source: {
        id: number;
        name: string;
        notes: string | null;
        host: string | null;
        database: string | null;
        files: { host: string; port: number; username: string; root: string; has_password: boolean; has_private_key: boolean } | null;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Migraciones', href: route('central.migrations.index', undefined, false) },
    { title: `Editar ${props.source.name}`, href: route('central.migrations.sources.edit', { source: props.source.id }, false) },
];

const form = useForm({
    name: props.source.name,
    files_host: props.source.files?.host ?? '',
    files_port: (props.source.files?.port ?? 22) as number | '',
    files_username: props.source.files?.username ?? '',
    files_password: '',
    files_private_key: '',
    files_root: props.source.files?.root ?? '',
    notes: props.source.notes ?? '',
});

const submit = () =>
    form.put(route('central.migrations.sources.update', { source: props.source.id }), {
        onFinish: () => form.reset('files_password', 'files_private_key'),
    });
</script>

<template>
    <CentralLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Editar ${source.name}`" />

        <Heading
            title="Editar fuente legacy"
            :description="`Conexión ${source.host} / ${source.database}. La conexión no se edita: para cambiarla registra otra fuente.`"
        />

        <form class="max-w-3xl space-y-8" @submit.prevent="submit">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">Nombre</Label>
                    <Input id="name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="notes">Notas</Label>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="3"
                        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    />
                    <InputError :message="form.errors.notes" />
                </div>
            </div>

            <LegacyFilesFields v-model="form" :errors="form.errors" :saved="source.files" :source-id="source.id" />

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Guardar cambios
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="route('central.migrations.index')">Cancelar</Link>
                </Button>
            </div>
        </form>
    </CentralLayout>
</template>
