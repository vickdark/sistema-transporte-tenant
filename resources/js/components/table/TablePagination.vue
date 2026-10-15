<script setup lang="ts">
import { type Paginated } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    rows: Paginated<unknown>;
    perPage: number;
    perPageOptions: number[];
    only?: string[];
}>();

const emit = defineEmits<{ 'update:perPage': [value: number] }>();

const previous = computed(() => props.rows.links[0]);
const next = computed(() => props.rows.links[props.rows.links.length - 1]);
const pages = computed(() => props.rows.links.slice(1, -1));

const linkClass = 'inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-sm';
</script>

<template>
    <div class="flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-muted-foreground">
            <span v-if="rows.total > 0">Mostrando {{ rows.from }}–{{ rows.to }} de {{ rows.total }}</span>
            <label class="flex items-center gap-2">
                Por página
                <select
                    :value="perPage"
                    class="h-8 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm"
                    @change="emit('update:perPage', Number(($event.target as HTMLSelectElement).value))"
                >
                    <option v-for="option in perPageOptions" :key="option" :value="option">{{ option }}</option>
                </select>
            </label>
        </div>

        <nav v-if="rows.last_page > 1" class="flex items-center gap-1" aria-label="Paginación">
            <component
                :is="previous.url ? Link : 'span'"
                :href="previous.url ?? undefined"
                :only="only"
                preserve-state
                preserve-scroll
                :class="[linkClass, previous.url ? 'hover:bg-muted' : 'pointer-events-none opacity-50']"
                aria-label="Página anterior"
            >
                <ChevronLeft class="size-4" />
            </component>

            <!-- En pantallas pequeñas basta con "página X de Y". -->
            <span class="px-2 tabular-nums text-muted-foreground md:hidden">{{ rows.current_page }} / {{ rows.last_page }}</span>
            <template v-for="link in pages" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    :only="only"
                    preserve-state
                    preserve-scroll
                    class="hidden md:inline-flex"
                    :class="[linkClass, link.active ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-muted']"
                >
                    {{ link.label }}
                </Link>
                <span v-else class="hidden px-1 text-muted-foreground md:inline">{{ link.label }}</span>
            </template>

            <component
                :is="next.url ? Link : 'span'"
                :href="next.url ?? undefined"
                :only="only"
                preserve-state
                preserve-scroll
                :class="[linkClass, next.url ? 'hover:bg-muted' : 'pointer-events-none opacity-50']"
                aria-label="Página siguiente"
            >
                <ChevronRight class="size-4" />
            </component>
        </nav>
    </div>
</template>
