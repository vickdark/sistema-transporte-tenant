<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { useConfirm } from '@/composables/useConfirm';
import { router } from '@inertiajs/vue3';
import { CircleCheck, ImageUp, LoaderCircle, Trash2, X } from 'lucide-vue-next';
import { computed, onUnmounted, ref } from 'vue';

/**
 * Carga de una imagen con vista previa antes de guardar: se elige (o se arrastra) el archivo, se
 * valida en el navegador, se compara con la imagen actual y solo se sube al confirmar, con
 * barra de progreso. El servidor vuelve a validar todo.
 */
const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        /** Imagen guardada actualmente (null si no hay). */
        currentUrl: string | null;
        /** Ruta POST que recibe el archivo en el campo `file`. */
        uploadUrl: string;
        /** Ruta DELETE para quitar la imagen. */
        deleteUrl: string;
        /** Fondo cuadriculado para ver bien imágenes con transparencia (p. ej. una firma). */
        transparent?: boolean;
        maxSizeMb?: number;
    }>(),
    {
        description: undefined,
        transparent: false,
        maxSizeMb: 2,
    },
);

const ACCEPTED_TYPES = ['image/png', 'image/jpeg'];

const input = ref<HTMLInputElement | null>(null);
const pending = ref<File | null>(null);
const previewUrl = ref<string | null>(null);
const error = ref<string | undefined>();
const progress = ref<number | null>(null);
const saved = ref(false);
const dragging = ref(false);

const uploading = computed(() => progress.value !== null);

const formatSize = (bytes: number): string => (bytes < 1024 * 1024 ? `${Math.round(bytes / 1024)} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`);

const clearPending = () => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }

    pending.value = null;
    previewUrl.value = null;

    if (input.value) {
        input.value.value = '';
    }
};

onUnmounted(clearPending);

const select = (file: File | undefined) => {
    saved.value = false;
    error.value = undefined;

    if (!file) {
        return;
    }

    if (!ACCEPTED_TYPES.includes(file.type)) {
        error.value = 'Solo se aceptan imágenes PNG o JPG.';
        return;
    }

    if (file.size > props.maxSizeMb * 1024 * 1024) {
        error.value = `La imagen pesa ${formatSize(file.size)}; el máximo es ${props.maxSizeMb} MB.`;
        return;
    }

    clearPending();
    pending.value = file;
    previewUrl.value = URL.createObjectURL(file);
};

const onDrop = (event: DragEvent) => {
    dragging.value = false;
    select(event.dataTransfer?.files[0]);
};

const save = () => {
    if (!pending.value) {
        return;
    }

    router.post(
        props.uploadUrl,
        { file: pending.value },
        {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => (progress.value = 0),
            onProgress: (event) => (progress.value = event?.percentage ?? null),
            onSuccess: () => {
                clearPending();
                saved.value = true;
                window.setTimeout(() => (saved.value = false), 3000);
            },
            onError: (errors) => (error.value = errors.file),
            onFinish: () => (progress.value = null),
        },
    );
};

const { confirm } = useConfirm();

const remove = async () => {
    const confirmed = await confirm({
        title: `¿Eliminar ${props.title.toLowerCase()}?`,
        text: 'Los documentos nuevos saldrán sin esta imagen hasta que subas otra.',
        confirmText: 'Eliminar',
        destructive: true,
    });

    if (confirmed) {
        router.delete(props.deleteUrl, { preserveScroll: true });
    }
};

const canvasClass = computed(() =>
    props.transparent
        ? 'bg-white bg-[length:16px_16px] bg-[image:conic-gradient(#e5e5e5_25%,transparent_0_50%,#e5e5e5_0_75%,transparent_0)]'
        : 'bg-muted/30',
);
</script>

<template>
    <article class="flex flex-col gap-4 rounded-xl border border-sidebar-border/70 p-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h3 class="text-sm font-medium">{{ title }}</h3>
                <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
            </div>
            <span v-if="saved" class="inline-flex shrink-0 items-center gap-1 text-xs font-medium text-emerald-700 dark:text-emerald-400">
                <CircleCheck class="size-4" />
                Guardada
            </span>
        </div>

        <!-- Imagen nueva sin guardar: comparación con la actual. -->
        <div v-if="pending && previewUrl" class="grid grid-cols-2 gap-3">
            <figure class="flex flex-col gap-1.5">
                <div class="flex h-28 items-center justify-center rounded-lg border border-sidebar-border p-2" :class="canvasClass">
                    <img v-if="currentUrl" :src="currentUrl" :alt="`${title} actual`" class="max-h-full max-w-full object-contain opacity-60" />
                    <span v-else class="text-xs text-muted-foreground">Sin imagen</span>
                </div>
                <figcaption class="text-center text-xs text-muted-foreground">Actual</figcaption>
            </figure>
            <figure class="flex flex-col gap-1.5">
                <div class="flex h-28 items-center justify-center rounded-lg border-2 border-primary p-2" :class="canvasClass">
                    <img :src="previewUrl" :alt="`${title} nueva`" class="max-h-full max-w-full object-contain" />
                </div>
                <figcaption class="truncate text-center text-xs font-medium" :title="pending.name">Nueva · {{ formatSize(pending.size) }}</figcaption>
            </figure>
        </div>

        <!-- Zona para elegir o arrastrar, con la imagen actual. -->
        <button
            v-else
            type="button"
            class="group relative flex h-36 flex-col items-center justify-center gap-2 overflow-hidden rounded-lg border-2 border-dashed p-3 text-center transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            :class="[dragging ? 'border-primary bg-primary/5' : 'border-sidebar-border hover:border-primary/60', canvasClass]"
            @click="input?.click()"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <template v-if="currentUrl">
                <img :src="currentUrl" :alt="title" class="max-h-full max-w-full object-contain" />
                <span
                    class="absolute inset-0 flex items-center justify-center gap-2 bg-background/80 text-sm font-medium opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100"
                >
                    <ImageUp class="size-4" />
                    Cambiar imagen
                </span>
            </template>
            <template v-else>
                <ImageUp class="size-6 text-muted-foreground" />
                <span class="text-sm"><span class="font-medium text-primary">Elige un archivo</span> o arrástralo aquí</span>
                <span class="text-xs text-muted-foreground">PNG o JPG, máximo {{ maxSizeMb }} MB</span>
            </template>
        </button>

        <input
            ref="input"
            type="file"
            accept="image/png,image/jpeg"
            class="sr-only"
            tabindex="-1"
            @change="select(($event.target as HTMLInputElement).files?.[0])"
        />

        <div v-if="uploading" class="flex flex-col gap-1">
            <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                <div class="h-full rounded-full bg-primary transition-all" :style="{ width: `${progress}%` }" />
            </div>
            <span class="text-xs tabular-nums text-muted-foreground">Subiendo… {{ progress }} %</span>
        </div>

        <InputError :message="error" />

        <div class="flex flex-wrap gap-2">
            <template v-if="pending">
                <button
                    type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
                    :disabled="uploading"
                    @click="save"
                >
                    <LoaderCircle v-if="uploading" class="size-4 animate-spin" />
                    <CircleCheck v-else class="size-4" />
                    Guardar imagen
                </button>
                <button
                    type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded-md border border-input px-3 text-xs font-medium hover:bg-muted disabled:opacity-60"
                    :disabled="uploading"
                    @click="clearPending"
                >
                    <X class="size-4" />
                    Descartar
                </button>
            </template>
            <template v-else>
                <button
                    type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded-md bg-blue-50 px-3 text-xs font-medium text-blue-700 hover:bg-blue-100 dark:bg-blue-950 dark:text-blue-300 dark:hover:bg-blue-900"
                    @click="input?.click()"
                >
                    <ImageUp class="size-4" />
                    {{ currentUrl ? 'Cambiar' : 'Elegir imagen' }}
                </button>
                <button
                    v-if="currentUrl"
                    type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded-md bg-red-50 px-3 text-xs font-medium text-red-700 hover:bg-red-100 dark:bg-red-950 dark:text-red-300 dark:hover:bg-red-900"
                    @click="remove"
                >
                    <Trash2 class="size-4" />
                    Eliminar
                </button>
            </template>
        </div>
    </article>
</template>
