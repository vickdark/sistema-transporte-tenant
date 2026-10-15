<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import CentralLayout from '@/layouts/CentralLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Migraciones', href: route('central.migrations.index', undefined, false) },
    { title: 'Registrar fuente', href: route('central.migrations.sources.create', undefined, false) },
];

const form = useForm({
    name: '',
    host: '127.0.0.1',
    port: 3306,
    database: '',
    username: '',
    password: '',
    notes: '',
});

const submit = () => {
    form.post(route('central.migrations.sources.store'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <CentralLayout :breadcrumbs="breadcrumbs">
        <Head title="Registrar fuente" />

        <Heading
            title="Registrar fuente legacy"
            description="Conexión MySQL a una copia de la base legacy. Usa un usuario con permisos de solo lectura: el sistema nunca escribe en la fuente."
        />

        <form class="max-w-3xl space-y-8" @submit.prevent="submit">
            <div class="grid gap-6 md:grid-cols-2">
                <div class="grid gap-2 md:col-span-2">
                    <Label for="name">Nombre</Label>
                    <Input id="name" v-model="form.name" required autofocus placeholder="Copia producción Kemuel" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="host">Servidor</Label>
                    <Input id="host" v-model="form.host" required />
                    <InputError :message="form.errors.host" />
                </div>

                <div class="grid gap-2">
                    <Label for="port">Puerto</Label>
                    <Input id="port" type="number" v-model="form.port" required />
                    <InputError :message="form.errors.port" />
                </div>

                <div class="grid gap-2 md:col-span-2">
                    <Label for="database">Base de datos</Label>
                    <Input id="database" v-model="form.database" required />
                    <InputError :message="form.errors.database" />
                </div>

                <div class="grid gap-2">
                    <Label for="username">Usuario</Label>
                    <Input id="username" v-model="form.username" required autocomplete="off" />
                    <InputError :message="form.errors.username" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Contraseña</Label>
                    <Input id="password" type="password" v-model="form.password" autocomplete="new-password" />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2 md:col-span-2">
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

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Registrar fuente
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="route('central.migrations.index')">Cancelar</Link>
                </Button>
            </div>
        </form>
    </CentralLayout>
</template>
