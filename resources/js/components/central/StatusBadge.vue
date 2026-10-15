<script setup lang="ts">
import { cn } from '@/lib/utils';
import { computed } from 'vue';

const props = defineProps<{
    status: string;
    label: string;
}>();

const tones: Record<string, string> = {
    active: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
    completed: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
    running: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
    pending: 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200',
    suspended: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    warning: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    quarantine: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    cancelled: 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200',
    failed: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
    error: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
};

const classes = computed(() => cn('inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium', tones[props.status] ?? tones.pending));
</script>

<template>
    <span :class="classes">{{ label }}</span>
</template>
