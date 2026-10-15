<script setup lang="ts" generic="T extends { id: string | number }">
import { Input } from '@/components/ui/input';
import { type Paginated, type TableColumn, type TableQuery } from '@/types';
import { router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown, FileSpreadsheet, FileText, LoaderCircle, Search } from 'lucide-vue-next';
import { computed, onUnmounted, reactive, ref, watch } from 'vue';
import TablePagination from './TablePagination.vue';

/**
 * Tabla de un listado resuelto en el servidor (App\Http\Tables\DataTable): búsqueda, filtros,
 * orden por columna, paginación y exportación a Excel/PDF con los mismos filtros y orden.
 *
 * - Escritorio (lg+): tabla. Móvil y tablet: una tarjeta por fila, separadas por una línea.
 * - Slots: `filters` ({ query, apply }) para filtros propios, `cell-<columna>` ({ row }) para
 *   personalizar una celda y `actions` ({ row, labeled }) para los botones de cada fila.
 */
const props = withDefaults(
    defineProps<{
        rows: Paginated<T>;
        columns: TableColumn[];
        query: TableQuery;
        /** URL del listado (sin parámetros) a la que se piden las páginas. */
        url: string;
        /** Props de la página que se recargan al cambiar búsqueda, filtros u orden. */
        only?: string[];
        /** URLs de exportación por formato (sin parámetros); sin ellas no se muestran los botones. */
        exportUrls?: { xlsx: string; pdf: string };
        searchPlaceholder?: string;
        emptyText?: string;
    }>(),
    {
        only: undefined,
        exportUrls: undefined,
        searchPlaceholder: 'Buscar…',
        emptyText: 'No hay registros que coincidan con la búsqueda.',
    },
);

const PER_PAGE_OPTIONS = [10, 15, 25, 50, 100];

const state = reactive<TableQuery>({ ...props.query });
const loading = ref(false);

// Después de cada visita el servidor devuelve el estado real (p. ej. un orden no permitido vuelve al de por defecto).
watch(
    () => props.query,
    (query) => Object.assign(state, query),
);

const params = computed(() => Object.fromEntries(Object.entries(state).filter(([, value]) => value !== null && value !== '' && value !== undefined)));

const apply = (changes: Partial<TableQuery> = {}) => {
    Object.assign(state, changes);

    router.get(props.url, params.value, {
        only: props.only,
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
};

let searchTimer: number | undefined;
watch(
    () => state.search,
    (search) => {
        if (search === props.query.search) {
            return;
        }

        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => apply(), 400);
    },
);
onUnmounted(() => window.clearTimeout(searchTimer));

const sortBy = (column: TableColumn) => {
    if (!column.sortable) {
        return;
    }

    apply({ sort: column.key, direction: state.sort === column.key && state.direction === 'asc' ? 'desc' : 'asc' });
};

const sortIcon = (column: TableColumn) => {
    if (state.sort !== column.key) {
        return ArrowUpDown;
    }

    return state.direction === 'asc' ? ArrowUp : ArrowDown;
};

const exportHref = (format: 'xlsx' | 'pdf'): string => {
    const search = new URLSearchParams(params.value as Record<string, string>).toString();

    return `${props.exportUrls?.[format]}${search ? `?${search}` : ''}`;
};

const primaryColumn = computed(() => props.columns.find((column) => column.primary) ?? props.columns[0]);
const secondaryColumns = computed(() => props.columns.filter((column) => column.key !== primaryColumn.value.key));

const cell = (row: T, key: string): unknown => (row as Record<string, unknown>)[key];
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <div class="relative w-full sm:w-72">
                    <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="state.search" type="search" class="pl-8" :placeholder="searchPlaceholder" :aria-label="searchPlaceholder" />
                </div>
                <slot name="filters" :query="state" :apply="apply" />
                <LoaderCircle v-if="loading" class="size-4 animate-spin text-muted-foreground" aria-label="Cargando" />
            </div>

            <div v-if="exportUrls" class="flex gap-2">
                <a
                    :href="exportHref('xlsx')"
                    class="inline-flex h-9 flex-1 items-center justify-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 text-sm font-medium text-emerald-800 hover:bg-emerald-100 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200 dark:hover:bg-emerald-900 sm:flex-none"
                >
                    <FileSpreadsheet class="size-4" />
                    Excel
                </a>
                <a
                    :href="exportHref('pdf')"
                    class="inline-flex h-9 flex-1 items-center justify-center gap-2 rounded-md border border-rose-200 bg-rose-50 px-3 text-sm font-medium text-rose-800 hover:bg-rose-100 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200 dark:hover:bg-rose-900 sm:flex-none"
                >
                    <FileText class="size-4" />
                    PDF
                </a>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-sidebar-border/70 transition-opacity" :class="{ 'opacity-60': loading }">
            <!-- Escritorio -->
            <div class="hidden overflow-x-auto lg:block">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/50 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th
                                v-for="column in columns"
                                :key="column.key"
                                class="whitespace-nowrap px-4 py-3 font-medium"
                                :class="{ 'text-right': column.align === 'right' }"
                                :aria-sort="state.sort === column.key ? (state.direction === 'asc' ? 'ascending' : 'descending') : undefined"
                            >
                                <button
                                    v-if="column.sortable"
                                    type="button"
                                    class="inline-flex items-center gap-1 uppercase hover:text-foreground"
                                    :class="{ 'text-foreground': state.sort === column.key }"
                                    @click="sortBy(column)"
                                >
                                    {{ column.label }}
                                    <component :is="sortIcon(column)" class="size-3.5" />
                                </button>
                                <template v-else>{{ column.label }}</template>
                            </th>
                            <th v-if="$slots.actions" class="px-4 py-3"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr v-for="row in rows.data" :key="row.id" class="hover:bg-muted/30">
                            <td
                                v-for="column in columns"
                                :key="column.key"
                                class="px-4 py-3"
                                :class="[column.align === 'right' ? 'text-right tabular-nums' : '', column.primary ? 'font-medium' : '']"
                            >
                                <slot :name="`cell-${column.key}`" :row="row">{{ cell(row, column.key) ?? '—' }}</slot>
                            </td>
                            <td v-if="$slots.actions" class="px-4 py-3">
                                <div class="flex justify-end gap-1.5">
                                    <slot name="actions" :row="row" :labeled="false" />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Móvil y tablet: una tarjeta por fila -->
            <ul class="divide-y divide-sidebar-border/70 lg:hidden">
                <li v-for="row in rows.data" :key="row.id" class="flex flex-col gap-3 p-4">
                    <div class="min-w-0 break-words font-medium">
                        <slot :name="`cell-${primaryColumn.key}`" :row="row">{{ cell(row, primaryColumn.key) ?? '—' }}</slot>
                    </div>
                    <dl v-if="secondaryColumns.length" class="grid grid-cols-1 gap-x-4 gap-y-2 text-sm min-[420px]:grid-cols-2">
                        <div v-for="column in secondaryColumns" :key="column.key" class="min-w-0">
                            <dt class="text-xs text-muted-foreground">{{ column.label }}</dt>
                            <dd class="break-words">
                                <slot :name="`cell-${column.key}`" :row="row">{{ cell(row, column.key) ?? '—' }}</slot>
                            </dd>
                        </div>
                    </dl>
                    <div v-if="$slots.actions" class="flex flex-wrap gap-2">
                        <slot name="actions" :row="row" :labeled="true" />
                    </div>
                </li>
            </ul>

            <p v-if="rows.data.length === 0" class="px-4 py-10 text-center text-sm text-muted-foreground">{{ emptyText }}</p>
        </div>

        <TablePagination
            :rows="rows"
            :per-page="state.per_page"
            :per-page-options="PER_PAGE_OPTIONS"
            :only="only"
            @update:per-page="(perPage) => apply({ per_page: perPage })"
        />
    </div>
</template>
