<script setup lang="ts">
/**
 * Estado de un contrato según la fecha de hoy: vigente (verde), futuro (azul) o vencido (gris).
 */
defineProps<{ status: 'current' | 'upcoming' | 'expired' }>();

const styles = {
    current: { label: 'Vigente', tone: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200' },
    upcoming: { label: 'Futuro', tone: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200' },
    expired: { label: 'Vencido', tone: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200' },
};
</script>

<template>
    <span class="whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium" :class="styles[status].tone">{{ styles[status].label }}</span>
</template>
