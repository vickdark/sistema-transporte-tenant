<script setup lang="ts">
/**
 * Estado de una ruta: activa (verde), en mantenimiento (ámbar) o inactiva (gris).
 */
defineProps<{ status: string; label: string }>();

const tones: Record<string, string> = {
    active: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
    maintenance: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    inactive: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
};
</script>

<template>
    <span class="whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium" :class="tones[status] ?? tones.inactive">{{ label }}</span>
</template>
