<script setup lang="ts">
import { Lock } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Rol de un usuario como etiqueta de color. Cada rol tiene siempre el mismo color (según su id)
 * para reconocerlo de un vistazo; el protegido (Administrador) va en violeta y con candado.
 */
const props = defineProps<{
    role: { id: number; name: string; is_protected: boolean } | null;
}>();

const palette = [
    'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
    'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
    'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    'bg-cyan-100 text-cyan-800 dark:bg-cyan-950 dark:text-cyan-200',
    'bg-pink-100 text-pink-800 dark:bg-pink-950 dark:text-pink-200',
    'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-200',
    'bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-200',
    'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-200',
    'bg-lime-100 text-lime-800 dark:bg-lime-950 dark:text-lime-200',
];

const tone = computed(() => {
    if (!props.role) {
        return 'border border-dashed border-amber-400 text-amber-700 dark:border-amber-700 dark:text-amber-300';
    }

    if (props.role.is_protected) {
        return 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-200';
    }

    return palette[props.role.id % palette.length];
});
</script>

<template>
    <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium" :class="tone">
        <Lock v-if="role?.is_protected" class="size-3" aria-hidden="true" />
        {{ role?.name ?? 'Sin rol' }}
    </span>
</template>
