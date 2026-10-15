<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ActionButton from '@/components/table/ActionButton.vue';
import DataTable from '@/components/table/DataTable.vue';
import ThirdPartyTypeBadge from '@/components/ThirdPartyTypeBadge.vue';
import { Button } from '@/components/ui/button';
import { type ThirdPartyKind, useThirdPartyLink } from '@/composables/useThirdPartyLink';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Paginated, type TableColumn, type TableQuery } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeftRight, Plus, Unlink } from 'lucide-vue-next';
import { computed } from 'vue';

interface Row {
    id: number;
    name: string;
    nit: string | null;
    phone: string | null;
    email: string | null;
    is_mixed: boolean;
    twin: { name: string; url: string | null } | null;
    can: { update: boolean; link: boolean };
    urls: { show: string; edit: string; convert: string; revert: string };
}

const props = defineProps<{
    kind: ThirdPartyKind;
    rows: Paginated<Row>;
    query: TableQuery;
    urls: { index: string; create: string; export_xlsx: string; export_pdf: string };
    can: { create: boolean };
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: props.kind.plural, href: props.urls.index }]);

const columns = computed<TableColumn[]>(() => [
    { key: 'name', label: 'Nombre', sortable: true, primary: true },
    { key: 'nit', label: props.kind.nit_label, sortable: true },
    { key: 'phone', label: 'Teléfono' },
    { key: 'email', label: 'Correo' },
    { key: 'type', label: 'Tipo' },
]);

const page = usePage();
const linkError = computed(() => (page.props.errors as Record<string, string>).link);

const { convert, revert } = useThirdPartyLink(props.kind);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="kind.plural" />

        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <Heading :title="kind.plural" :description="`${kind.plural} registrados y su vínculo con ${kind.twin_plural.toLowerCase()}.`" />
                <Button v-if="can.create" as-child class="w-full sm:w-auto">
                    <Link :href="urls.create">
                        <Plus />
                        Agregar {{ kind.singular }}
                    </Link>
                </Button>
            </div>

            <!-- Mismo aviso que el legacy: qué hacen Convertir y Revertir. -->
            <div class="grid gap-3 rounded-xl border border-sidebar-border/70 bg-muted/30 p-4 text-sm md:grid-cols-2">
                <p class="flex gap-2">
                    <ArrowLeftRight class="mt-0.5 size-4 shrink-0 text-violet-600 dark:text-violet-400" />
                    <span>
                        <strong>Convertir:</strong> lo registra también en {{ kind.twin_plural }} y lo vuelve <strong>mixto</strong>, para que opere
                        en los dos perfiles con los mismos datos.
                    </span>
                </p>
                <p class="flex gap-2">
                    <Unlink class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                    <span>
                        <strong>Revertir:</strong> deja de ser mixto y vuelve a ser un {{ kind.singular }} sencillo. Su registro como
                        {{ kind.twin_singular }} sigue existiendo por separado.
                    </span>
                </p>
            </div>

            <InputError :message="linkError" />

            <DataTable
                :rows="rows"
                :columns="columns"
                :query="query"
                :url="urls.index"
                :only="['rows', 'query']"
                :export-urls="{ xlsx: urls.export_xlsx, pdf: urls.export_pdf }"
                :search-placeholder="`Buscar por nombre, ${kind.nit_label}, teléfono o correo`"
                :empty-text="`No hay ${kind.plural.toLowerCase()} que coincidan con la búsqueda.`"
            >
                <template #filters="{ query: state, apply }">
                    <select
                        :value="state.type ?? ''"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm sm:w-auto"
                        aria-label="Tipo"
                        @change="apply({ type: ($event.target as HTMLSelectElement).value || null })"
                    >
                        <option value="">Todos los tipos</option>
                        <option value="mixed">Mixtos</option>
                        <option value="single">Sencillos</option>
                    </select>
                </template>

                <template #cell-name="{ row }">
                    <Link :href="row.urls.show" class="hover:underline">{{ row.name }}</Link>
                </template>
                <template #cell-nit="{ row }">
                    <span class="tabular-nums">{{ row.nit ?? '—' }}</span>
                </template>
                <template #cell-email="{ row }">
                    <span class="text-muted-foreground">{{ row.email ?? '—' }}</span>
                </template>
                <template #cell-type="{ row }">
                    <ThirdPartyTypeBadge :mixed="row.is_mixed" />
                </template>

                <template #actions="{ row, labeled }">
                    <ActionButton action="view" :labeled="labeled" :label="`Ver ${row.name}`" :href="row.urls.show" />
                    <ActionButton v-if="row.can.update" action="edit" :labeled="labeled" :label="`Editar ${row.name}`" :href="row.urls.edit" />
                    <template v-if="row.can.link">
                        <ActionButton
                            v-if="row.is_mixed"
                            action="revert"
                            :labeled="labeled"
                            :label="`Revertir conversión de ${row.name}`"
                            @click="revert(row.name, row.urls)"
                        />
                        <ActionButton
                            v-else
                            action="convert"
                            :labeled="labeled"
                            :label="`Convertir ${row.name} en ${kind.twin_singular}`"
                            @click="convert(row.name, row.urls)"
                        />
                    </template>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
