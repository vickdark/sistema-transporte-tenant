<script setup lang="ts">
import { ArrowLeftRight } from 'lucide-vue-next';

/**
 * Tipo de un aliado o proveedor: "Mixto" (registrado en los dos módulos y vinculado) o "Sencillo".
 */
defineProps<{ mixed: boolean }>();
</script>

<template>
    <span
        v-if="mixed"
        class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-violet-100 px-2.5 py-0.5 text-xs font-medium text-violet-800 dark:bg-violet-950 dark:text-violet-200"
    >
        <ArrowLeftRight class="size-3" aria-hidden="true" />
        Mixto
    </span>
    <span
        v-else
        class="inline-flex items-center whitespace-nowrap rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200"
    >
        Sencillo
    </span>
</template>
