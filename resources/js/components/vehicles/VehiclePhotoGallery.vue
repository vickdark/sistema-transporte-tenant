<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { useConfirm } from '@/composables/useConfirm';
import { type VehiclePhotoData } from '@/types/vehicles';
import { router, useForm } from '@inertiajs/vue3';
import { ImagePlus, LoaderCircle, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * Fotos del vehículo (hasta `max`, como en el legacy): galería con vista ampliada, agregar varias
 * a la vez y eliminar.
 */
const props = defineProps<{
    vehicleId: number;
    plate: string;
    photos: VehiclePhotoData[];
    canUpdate: boolean;
    max: number;
    maxFileMb: number;
}>();

const input = ref<HTMLInputElement | null>(null);
const enlarged = ref<VehiclePhotoData | null>(null);
const form = useForm({ photos: [] as File[] });

const available = computed(() => Math.max(props.max - props.photos.length, 0));
const error = computed(() => form.errors.photos ?? Object.entries(form.errors).find(([key]) => key.startsWith('photos.'))?.[1]);

const upload = (event: Event) => {
    const files = Array.from((event.target as HTMLInputElement).files ?? []);

    if (files.length === 0) {
        return;
    }

    form.photos = files;
    form.post(route('vehicles.photos.store', { vehicle: props.vehicleId }), {
        preserveScroll: true,
        onFinish: () => {
            form.photos = [];

            if (input.value) {
                input.value.value = '';
            }
        },
    });
};

const { confirm } = useConfirm();

const remove = async (photo: VehiclePhotoData) => {
    const confirmed = await confirm({ title: '¿Eliminar la foto?', text: 'No se puede deshacer.', confirmText: 'Eliminar', destructive: true });

    if (confirmed) {
        router.delete(route('vehicles.photos.destroy', { vehicle: props.vehicleId, photo: photo.id }), { preserveScroll: true });
    }
};
</script>

<template>
    <div class="flex flex-col gap-3">
        <div v-if="photos.length > 0" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <figure
                v-for="(photo, index) in photos"
                :key="photo.id"
                class="group relative overflow-hidden rounded-lg border border-sidebar-border/70 bg-muted/30"
            >
                <button
                    type="button"
                    class="block aspect-[4/3] w-full"
                    :aria-label="`Ampliar foto ${index + 1} de ${plate}`"
                    @click="enlarged = photo"
                >
                    <img :src="photo.url" :alt="`Foto ${index + 1} de ${plate}`" class="size-full object-cover" loading="lazy" />
                </button>
                <button
                    v-if="canUpdate"
                    type="button"
                    class="absolute right-1.5 top-1.5 rounded-md bg-background/90 p-1.5 text-red-700 opacity-100 shadow-sm hover:bg-background dark:text-red-300 sm:opacity-0 sm:focus-visible:opacity-100 sm:group-hover:opacity-100"
                    :aria-label="`Eliminar foto ${index + 1}`"
                    @click="remove(photo)"
                >
                    <Trash2 class="size-4" />
                </button>
            </figure>
        </div>
        <p v-else class="text-sm text-muted-foreground">No hay fotos del vehículo.</p>

        <div v-if="canUpdate" class="flex flex-wrap items-center gap-3">
            <button
                type="button"
                class="inline-flex h-8 items-center gap-1.5 rounded-md bg-blue-50 px-3 text-xs font-medium text-blue-700 hover:bg-blue-100 disabled:opacity-60 dark:bg-blue-950 dark:text-blue-300 dark:hover:bg-blue-900"
                :disabled="available === 0 || form.processing"
                @click="input?.click()"
            >
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                <ImagePlus v-else class="size-4" />
                Agregar fotos
            </button>
            <span class="text-xs text-muted-foreground">
                {{ available > 0 ? `Puedes agregar ${available} más (JPG, PNG o WEBP, máximo ${maxFileMb} MB cada una).` : `Máximo ${max} fotos.` }}
            </span>
            <input ref="input" type="file" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" tabindex="-1" @change="upload" />
        </div>
        <InputError :message="error" />

        <Dialog :open="enlarged !== null" @update:open="(value) => !value && (enlarged = null)">
            <DialogContent class="max-w-4xl p-2">
                <DialogTitle class="sr-only">Foto de {{ plate }}</DialogTitle>
                <img v-if="enlarged" :src="enlarged.url" :alt="`Foto de ${plate}`" class="max-h-[80vh] w-full rounded-md object-contain" />
            </DialogContent>
        </Dialog>
    </div>
</template>
