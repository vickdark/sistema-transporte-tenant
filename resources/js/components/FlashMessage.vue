<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CircleCheck, TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();
const success = computed(() => page.props.flash.success);
const warning = computed(() => page.props.flash.warning);
</script>

<template>
    <div v-if="success || warning" class="flex flex-col gap-3">
        <div
            v-if="success"
            class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200"
        >
            <CircleCheck class="size-4 shrink-0" />
            {{ success }}
        </div>
        <div
            v-if="warning"
            class="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
        >
            <TriangleAlert class="size-4 shrink-0" />
            {{ warning }}
        </div>
    </div>
</template>
