<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useConfirm } from '@/composables/useConfirm';
import CentralLayout from '@/layouts/CentralLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

interface Option<T = string> {
    value: T;
    label: string;
}

interface ModuleOption extends Option {
    depends_on: string[];
}

const props = defineProps<{
    sources: Option<number>[];
    tenants: Option[];
    modules: ModuleOption[];
    modes: Option[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Migraciones', href: route('central.migrations.index', undefined, false) },
    { title: 'Nueva corrida', href: route('central.migrations.runs.create', undefined, false) },
];

const form = useForm({
    migration_source_id: props.sources[0]?.value ?? '',
    tenant_id: '',
    mode: 'dry_run',
    modules: props.modules.map((module) => module.value),
});

const selectClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm';

const missingDependencies = computed(() =>
    props.modules
        .filter((module) => form.modules.includes(module.value))
        .flatMap((module) =>
            module.depends_on
                .filter((dependency) => !form.modules.includes(dependency))
                .map((dependency) => `${module.label} necesita ${props.modules.find((item) => item.value === dependency)?.label ?? dependency}`),
        ),
);

const { confirm } = useConfirm();

const submit = async () => {
    if (form.mode === 'live') {
        const tenant = props.tenants.find((item) => item.value === form.tenant_id)?.label ?? 'la empresa';
        const confirmed = await confirm({
            title: '¿Ejecutar una corrida real?',
            text: `Se escribirá en ${tenant}. Repetirla es seguro (actualiza, no duplica), pero lo importado queda en la empresa.`,
            confirmText: 'Ejecutar',
            destructive: true,
        });

        if (!confirmed) {
            return;
        }
    }

    form.post(route('central.migrations.runs.store'));
};
</script>

<template>
    <CentralLayout :breadcrumbs="breadcrumbs">
        <Head title="Nueva corrida" />

        <Heading
            title="Nueva corrida de migración"
            description="Lee la fuente legacy (solo lectura) y la importa en la empresa. Antes de una corrida real, haz una simulación y revisa la cuarentena."
        />

        <form class="max-w-3xl space-y-8" @submit.prevent="submit">
            <div class="grid gap-6 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="source">Fuente</Label>
                    <select id="source" v-model="form.migration_source_id" :class="selectClass" required>
                        <option v-for="source in sources" :key="source.value" :value="source.value">{{ source.label }}</option>
                    </select>
                    <InputError :message="form.errors.migration_source_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="tenant">Empresa destino</Label>
                    <select id="tenant" v-model="form.tenant_id" :class="selectClass" required>
                        <option value="" disabled>Selecciona una empresa</option>
                        <option v-for="tenant in tenants" :key="tenant.value" :value="tenant.value">{{ tenant.label }}</option>
                    </select>
                    <InputError :message="form.errors.tenant_id" />
                </div>
            </div>

            <fieldset class="space-y-3">
                <legend class="text-sm font-medium">Módulos</legend>
                <label v-for="module in modules" :key="module.value" class="flex items-center gap-2 text-sm">
                    <input v-model="form.modules" type="checkbox" :value="module.value" class="size-4 rounded border-input" />
                    {{ module.label }}
                </label>
                <p v-for="message in missingDependencies" :key="message" class="text-sm text-amber-700 dark:text-amber-300">
                    {{ message }}: solo funciona si ya se migró en una corrida real.
                </p>
                <InputError :message="form.errors.modules" />
            </fieldset>

            <fieldset class="space-y-3">
                <legend class="text-sm font-medium">Modo</legend>
                <label v-for="mode in modes" :key="mode.value" class="flex items-center gap-2 text-sm">
                    <input v-model="form.mode" type="radio" name="mode" :value="mode.value" class="size-4" />
                    {{ mode.label }}
                    <span class="text-muted-foreground">
                        {{ mode.value === 'dry_run' ? '— no escribe nada; solo produce contadores y reporte.' : '— escribe en la empresa.' }}
                    </span>
                </label>
                <InputError :message="form.errors.mode" />
            </fieldset>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing || sources.length === 0">
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    Iniciar corrida
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="route('central.migrations.index')">Cancelar</Link>
                </Button>
            </div>
            <p v-if="sources.length === 0" class="text-sm text-muted-foreground">Primero registra una fuente legacy.</p>
        </form>
    </CentralLayout>
</template>
