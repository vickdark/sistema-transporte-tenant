<script setup lang="ts">
import { type SearchSelectOption } from '@/types';
import { LoaderCircle, Search, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * Select con buscador: al escribir consulta `searchUrl?search=…` (JSON con value, label y
 * description) y muestra las coincidencias en una lista desplegable. Se maneja con el teclado
 * (flechas, Enter, Escape). Sin texto muestra las primeras opciones.
 */
const props = withDefaults(
    defineProps<{
        searchUrl: string;
        placeholder?: string;
        id?: string;
    }>(),
    {
        placeholder: 'Buscar…',
        id: undefined,
    },
);

const selected = defineModel<SearchSelectOption | null>({ default: null });

const root = ref<HTMLElement | null>(null);
const search = ref('');
const options = ref<SearchSelectOption[]>([]);
const open = ref(false);
const loading = ref(false);
const highlighted = ref(0);
let timer: number | undefined;
let requestId = 0;

const listId = computed(() => `${props.id ?? 'search-select'}-options`);

const load = async (term: string) => {
    const current = ++requestId;
    loading.value = true;

    try {
        const response = await fetch(`${props.searchUrl}?search=${encodeURIComponent(term)}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });

        // Solo cuenta la respuesta de la última búsqueda.
        if (current === requestId) {
            options.value = response.ok ? await response.json() : [];
            highlighted.value = 0;
        }
    } finally {
        if (current === requestId) {
            loading.value = false;
        }
    }
};

watch(search, (term) => {
    open.value = true;
    window.clearTimeout(timer);
    timer = window.setTimeout(() => load(term), 300);
});

const openList = () => {
    open.value = true;

    if (options.value.length === 0) {
        load(search.value);
    }
};

const choose = (option: SearchSelectOption) => {
    selected.value = option;
    open.value = false;
    search.value = '';
};

const clear = () => {
    selected.value = null;
};

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        open.value = true;
        highlighted.value = Math.min(highlighted.value + 1, options.value.length - 1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        highlighted.value = Math.max(highlighted.value - 1, 0);
    } else if (event.key === 'Enter' && open.value && options.value[highlighted.value]) {
        event.preventDefault();
        choose(options.value[highlighted.value]);
    } else if (event.key === 'Escape') {
        open.value = false;
    }
};

const onClickOutside = (event: MouseEvent) => {
    if (root.value && !root.value.contains(event.target as Node)) {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('mousedown', onClickOutside));
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onClickOutside);
    window.clearTimeout(timer);
});
</script>

<template>
    <div ref="root" class="relative">
        <div v-if="selected" class="flex items-center justify-between gap-3 rounded-md border border-input bg-background px-3 py-2 shadow-sm">
            <div class="min-w-0">
                <p class="break-words text-sm font-medium">{{ selected.label }}</p>
                <p v-if="selected.description" class="text-xs text-muted-foreground">{{ selected.description }}</p>
            </div>
            <button type="button" class="shrink-0 rounded-md p-1 text-muted-foreground hover:bg-muted" aria-label="Quitar selección" @click="clear">
                <X class="size-4" />
            </button>
        </div>

        <template v-else>
            <Search class="pointer-events-none absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
            <input
                :id="id"
                v-model="search"
                type="text"
                role="combobox"
                autocomplete="off"
                :aria-expanded="open"
                :aria-controls="listId"
                :placeholder="placeholder"
                class="h-9 w-full rounded-md border border-input bg-background pl-8 pr-8 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                @focus="openList"
                @keydown="onKeydown"
            />
            <LoaderCircle v-if="loading" class="absolute right-2.5 top-2.5 size-4 animate-spin text-muted-foreground" />

            <ul
                v-if="open"
                :id="listId"
                role="listbox"
                class="absolute z-20 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-sidebar-border bg-background py-1 shadow-lg"
            >
                <li
                    v-for="(option, index) in options"
                    :key="option.value"
                    role="option"
                    :aria-selected="index === highlighted"
                    class="cursor-pointer px-3 py-2 text-sm"
                    :class="index === highlighted ? 'bg-muted' : 'hover:bg-muted/60'"
                    @mouseenter="highlighted = index"
                    @mousedown.prevent="choose(option)"
                >
                    <span class="block font-medium">{{ option.label }}</span>
                    <span v-if="option.description" class="block text-xs text-muted-foreground">{{ option.description }}</span>
                </li>
                <li v-if="!loading && options.length === 0" class="px-3 py-4 text-center text-sm text-muted-foreground">Sin coincidencias.</li>
            </ul>
        </template>
    </div>
</template>
