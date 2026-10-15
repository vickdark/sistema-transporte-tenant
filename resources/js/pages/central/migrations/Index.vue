<script setup lang="ts">
import StatusBadge from '@/components/central/StatusBadge.vue';
import Heading from '@/components/Heading.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/composables/useConfirm';
import CentralLayout from '@/layouts/CentralLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

interface SourceRow {
    id: number;
    name: string;
    type_label: string;
    host: string | null;
    database: string | null;
    runs_count: number;
    created_at: string | null;
}

interface RunRow {
    id: number;
    tenant: string;
    source: string;
    mode_label: string;
    status: string;
    status_label: string;
    created_at: string | null;
}

defineProps<{
    sources: SourceRow[];
    runs: RunRow[];
}>();

const page = usePage();
const sourceError = computed(() => (page.props.errors as Record<string, string>).source);

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Migraciones', href: route('central.migrations.index', undefined, false) }];

const { confirm } = useConfirm();

const removeSource = async (source: SourceRow) => {
    const confirmed = await confirm({
        title: `¿Eliminar la fuente ${source.name}?`,
        text: 'Se borran la conexión y sus credenciales guardadas.',
        confirmText: 'Eliminar',
        destructive: true,
    });

    if (!confirmed) {
        return;
    }

    router.delete(route('central.migrations.sources.destroy', { source: source.id }), { preserveScroll: true });
};
</script>

<template>
    <CentralLayout :breadcrumbs="breadcrumbs">
        <Head title="Migraciones" />

        <Heading
            title="Migración de datos"
            description="Traslado de datos desde un sistema legacy hacia una empresa. Los importadores se agregan a medida que se construye cada módulo."
        />

        <section class="space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <HeadingSmall
                    title="Fuentes legacy"
                    description="Conexiones de solo lectura a una copia de la base de datos legacy. Las credenciales se guardan cifradas."
                />
                <Button variant="outline" as-child>
                    <Link :href="route('central.migrations.sources.create')">
                        <Plus />
                        Registrar fuente
                    </Link>
                </Button>
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/50 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Nombre</th>
                            <th class="px-4 py-3 font-medium">Tipo</th>
                            <th class="px-4 py-3 font-medium">Servidor / base</th>
                            <th class="px-4 py-3 text-right font-medium">Corridas</th>
                            <th class="px-4 py-3"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr v-for="source in sources" :key="source.id">
                            <td class="px-4 py-3 font-medium">{{ source.name }}</td>
                            <td class="px-4 py-3">{{ source.type_label }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ source.host }} / {{ source.database }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ source.runs_count }}</td>
                            <td class="px-4 py-3 text-right">
                                <Button
                                    v-if="source.runs_count === 0"
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="`Eliminar ${source.name}`"
                                    @click="removeSource(source)"
                                >
                                    <Trash2 />
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="sources.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">Todavía no hay fuentes registradas.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <InputError :message="sourceError" />
        </section>

        <section class="space-y-4">
            <HeadingSmall
                title="Corridas"
                description="Cada ejecución (simulación o real) queda registrada con sus pasos, contadores y cuarentena."
            />

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/50 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">#</th>
                            <th class="px-4 py-3 font-medium">Empresa</th>
                            <th class="px-4 py-3 font-medium">Fuente</th>
                            <th class="px-4 py-3 font-medium">Modo</th>
                            <th class="px-4 py-3 font-medium">Estado</th>
                            <th class="px-4 py-3 font-medium">Creada</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr v-for="run in runs" :key="run.id" class="hover:bg-muted/30">
                            <td class="px-4 py-3 tabular-nums">
                                <Link :href="route('central.migrations.runs.show', { run: run.id })" class="font-medium hover:underline">{{
                                    run.id
                                }}</Link>
                            </td>
                            <td class="px-4 py-3">{{ run.tenant }}</td>
                            <td class="px-4 py-3">{{ run.source }}</td>
                            <td class="px-4 py-3">{{ run.mode_label }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="run.status" :label="run.status_label" /></td>
                            <td class="px-4 py-3 text-muted-foreground">{{ run.created_at }}</td>
                        </tr>
                        <tr v-if="runs.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">
                                Aún no hay corridas. Se podrán ejecutar cuando exista el primer importador (Fase 1: usuarios y roles).
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </CentralLayout>
</template>
