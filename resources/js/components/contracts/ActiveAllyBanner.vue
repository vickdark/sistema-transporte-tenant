<script setup lang="ts">
import { type ContractModule } from '@/types/contracts';
import { router } from '@inertiajs/vue3';
import { Handshake } from 'lucide-vue-next';

/**
 * Contratos de aliados: con qué aliado se está trabajando (como en el legacy, "Gestionando
 * información del aliado X") y, para usuarios de la empresa, el botón para cambiarlo.
 */
const props = defineProps<{ module: ContractModule }>();

const changeAlly = () => {
    if (props.module.urls.forget_ally) {
        router.delete(props.module.urls.forget_ally);
    }
};
</script>

<template>
    <div
        v-if="module.ally"
        class="flex flex-col gap-2 rounded-xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm text-violet-900 dark:border-violet-900 dark:bg-violet-950 dark:text-violet-100 sm:flex-row sm:items-center sm:justify-between"
    >
        <p class="flex items-center gap-2">
            <Handshake class="size-4 shrink-0" />
            <span>
                Gestionando información del aliado: <strong>{{ module.ally.name }}</strong>
            </span>
        </p>
        <button
            v-if="module.can_change_ally"
            type="button"
            class="self-start rounded-md border border-violet-300 px-3 py-1 text-xs font-medium hover:bg-violet-100 dark:border-violet-800 dark:hover:bg-violet-900 sm:self-auto"
            @click="changeAlly"
        >
            Cambiar aliado
        </button>
    </div>
</template>
