<script setup lang="ts">
import { ArrowLeftRight } from 'lucide-vue-next';

/**
 * Tipo de dueño de un vehículo: empresa (verde), proveedor (azul), aliado (ámbar) o mixto (violeta),
 * con los mismos colores que el legacy.
 */
defineProps<{ type: string; label: string }>();

const tones: Record<string, string> = {
    company: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
    supplier: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200',
    ally: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    mixed: 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-200',
};
</script>

<template>
    <span
        class="inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium"
        :class="tones[type] ?? tones.company"
    >
        <ArrowLeftRight v-if="type === 'mixed'" class="size-3" aria-hidden="true" />
        {{ label }}
    </span>
</template>
