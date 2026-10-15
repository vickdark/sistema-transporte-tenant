<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Eye, Pencil, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Botón de acción de una fila, con un color por tipo de acción: ver (gris), editar (azul) y
 * eliminar (rojo). En la tabla de escritorio muestra solo el ícono; en las tarjetas de móvil
 * (`labeled`) también el texto.
 */
const props = defineProps<{
    action: 'view' | 'edit' | 'delete';
    label: string;
    href?: string;
    labeled?: boolean;
}>();

const emit = defineEmits<{ click: [] }>();

const tones = {
    view: 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700',
    edit: 'bg-blue-50 text-blue-700 hover:bg-blue-100 dark:bg-blue-950 dark:text-blue-300 dark:hover:bg-blue-900',
    delete: 'bg-red-50 text-red-700 hover:bg-red-100 dark:bg-red-950 dark:text-red-300 dark:hover:bg-red-900',
};

const texts = { view: 'Ver', edit: 'Editar', delete: 'Eliminar' };
const icons = { view: Eye, edit: Pencil, delete: Trash2 };

const classes = computed(() => [
    'inline-flex h-8 items-center justify-center gap-1.5 rounded-md text-xs font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring',
    props.labeled ? 'px-3' : 'w-8',
    tones[props.action],
]);
</script>

<template>
    <Link v-if="href" :href="href" :class="classes" :aria-label="label" :title="label">
        <component :is="icons[action]" class="size-4" />
        <span v-if="labeled">{{ texts[action] }}</span>
    </Link>
    <button v-else type="button" :class="classes" :aria-label="label" :title="label" @click="emit('click')">
        <component :is="icons[action]" class="size-4" />
        <span v-if="labeled">{{ texts[action] }}</span>
    </button>
</template>
