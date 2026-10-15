<script setup lang="ts">
import { type LucideIcon } from 'lucide-vue-next';

/**
 * Sección de un formulario largo: tarjeta con ícono, título y descripción. El `id` sirve de ancla
 * para el índice de secciones; `has-errors` la resalta cuando tiene campos por corregir.
 */
defineProps<{
    id: string;
    title: string;
    description?: string;
    icon: LucideIcon;
    hasErrors?: boolean;
}>();
</script>

<template>
    <section
        :id="id"
        class="scroll-mt-6 rounded-xl border bg-card p-4 transition-colors sm:p-6"
        :class="hasErrors ? 'border-red-300 dark:border-red-800' : 'border-sidebar-border/70'"
    >
        <header class="mb-5 flex items-start gap-3">
            <span
                class="flex size-9 shrink-0 items-center justify-center rounded-lg"
                :class="hasErrors ? 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300' : 'bg-muted text-muted-foreground'"
            >
                <component :is="icon" class="size-5" />
            </span>
            <div class="min-w-0">
                <h3 class="text-base font-medium">{{ title }}</h3>
                <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
            </div>
        </header>
        <slot />
    </section>
</template>
