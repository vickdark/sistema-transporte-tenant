<script setup lang="ts">
/**
 * Estado de un documento según su vencimiento: vigente, por vencer (30 días o menos), vencido o
 * sin vencimiento.
 */
defineProps<{ status: 'valid' | 'expiring' | 'expired' | 'no_expiry' }>();

const styles = {
    valid: { label: 'Vigente', tone: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200' },
    expiring: { label: 'Por vencer', tone: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200' },
    expired: { label: 'Vencido', tone: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200' },
    no_expiry: { label: 'Sin vencimiento', tone: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200' },
};
</script>

<template>
    <span class="whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium" :class="styles[status].tone">{{ styles[status].label }}</span>
</template>
