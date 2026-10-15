<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ChevronDown, FileText } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

export interface DocumentTypeInfo {
    type: string;
    label: string;
    group: string;
    expires: boolean;
    field_labels: { number: string; issued_on: string; expires_on: string };
}

export interface DocumentFormValue {
    number: string;
    issued_on: string;
    expires_on: string;
    file: File | null;
}

export interface CurrentDocument {
    number: string | null;
    issued_on: string | null;
    expires_on: string | null;
    file_url: string | null;
}

/** Días antes del vencimiento en que se muestra "por vencer" (como VehicleDocument::EXPIRING_DAYS). */
const EXPIRING_DAYS = 30;

/**
 * Un documento dentro del formulario del vehículo, plegable: cerrado muestra su estado y un resumen;
 * abierto, número, fechas y archivo. Si se deja vacío no se toca; al editar corrige el vigente
 * (renovar es desde la ficha). Se abre solo cuando tiene errores.
 */
const props = defineProps<{
    document: DocumentTypeInfo;
    /** Documento vigente (al editar). */
    current: CurrentDocument | null;
    errors: { number?: string; issued_on?: string; expires_on?: string; file?: string };
    maxFileMb: number;
}>();

const value = defineModel<DocumentFormValue>({ required: true });
const input = ref<HTMLInputElement | null>(null);

const hasErrors = computed(() => Object.values(props.errors).some(Boolean));
const open = ref(hasErrors.value);
watch(hasErrors, (errors) => errors && (open.value = true));

const isFilled = computed(() => Boolean(value.value.number || value.value.issued_on || value.value.expires_on || value.value.file));

const hasChanges = computed(
    () =>
        value.value.file !== null ||
        value.value.number !== (props.current?.number ?? '') ||
        value.value.issued_on !== (props.current?.issued_on ?? '') ||
        value.value.expires_on !== (props.current?.expires_on ?? ''),
);

const status = computed(() => {
    if (!isFilled.value && !props.current) {
        return { label: 'Sin registrar', tone: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' };
    }

    if (!props.document.expires) {
        return { label: 'Registrada', tone: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200' };
    }

    if (!value.value.expires_on) {
        return { label: 'Falta el vencimiento', tone: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200' };
    }

    const days = Math.floor((new Date(`${value.value.expires_on}T00:00:00`).getTime() - new Date().setHours(0, 0, 0, 0)) / 86_400_000);

    if (days < 0) {
        return { label: 'Vencido', tone: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200' };
    }

    return days <= EXPIRING_DAYS
        ? { label: 'Por vencer', tone: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200' }
        : { label: 'Vigente', tone: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200' };
});

const formatDate = (date: string) => new Date(`${date}T00:00:00`).toLocaleDateString('es-CO', { dateStyle: 'medium' });

const summary = computed(() => {
    const parts = [
        value.value.number,
        props.document.expires && value.value.expires_on ? `vence ${formatDate(value.value.expires_on)}` : null,
        value.value.file ? `archivo nuevo: ${value.value.file.name}` : props.current?.file_url ? 'con archivo' : null,
    ].filter(Boolean);

    return parts.length > 0 ? parts.join(' · ') : 'Opcional';
});

const onFile = (event: Event) => {
    value.value.file = (event.target as HTMLInputElement).files?.[0] ?? null;
};

const clearFile = () => {
    value.value.file = null;

    if (input.value) {
        input.value.value = '';
    }
};

const id = (field: string) => `documents-${props.document.type}-${field}`;
</script>

<template>
    <div class="rounded-lg border transition-colors" :class="hasErrors ? 'border-red-300 dark:border-red-800' : 'border-sidebar-border/70'">
        <button
            type="button"
            class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-muted/40"
            :aria-expanded="open"
            :aria-controls="id('panel')"
            @click="open = !open"
        >
            <div class="min-w-0 flex-1">
                <p class="flex flex-wrap items-center gap-2 text-sm font-medium">
                    {{ document.label }}
                    <span v-if="hasChanges && isFilled" class="text-xs font-normal text-primary">· cambios sin guardar</span>
                </p>
                <p class="truncate text-xs text-muted-foreground">{{ summary }}</p>
            </div>
            <span class="shrink-0 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium" :class="status.tone">{{ status.label }}</span>
            <ChevronDown class="size-4 shrink-0 text-muted-foreground transition-transform" :class="{ 'rotate-180': open }" />
        </button>

        <div v-show="open" :id="id('panel')" class="grid gap-3 border-t border-sidebar-border/70 p-4 sm:grid-cols-2">
            <div class="grid gap-1.5 sm:col-span-2">
                <Label :for="id('number')">{{ document.field_labels.number }}{{ document.expires ? '' : ' *' }}</Label>
                <Input :id="id('number')" v-model="value.number" maxlength="50" />
                <InputError :message="errors.number" />
            </div>
            <div class="grid gap-1.5">
                <Label :for="id('issued_on')">{{ document.field_labels.issued_on }}</Label>
                <Input :id="id('issued_on')" v-model="value.issued_on" type="date" />
                <InputError :message="errors.issued_on" />
            </div>
            <div v-if="document.expires" class="grid gap-1.5">
                <Label :for="id('expires_on')">{{ document.field_labels.expires_on }} *</Label>
                <Input :id="id('expires_on')" v-model="value.expires_on" type="date" />
                <InputError :message="errors.expires_on" />
            </div>
            <div class="grid gap-1.5 sm:col-span-2">
                <Label :for="id('file')">Archivo (PDF o imagen, máximo {{ maxFileMb }} MB)</Label>
                <div class="flex flex-wrap items-center gap-2">
                    <input
                        :id="id('file')"
                        ref="input"
                        type="file"
                        accept="application/pdf,image/png,image/jpeg"
                        class="min-w-0 flex-1 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-sm file:font-medium"
                        @change="onFile"
                    />
                    <button v-if="value.file" type="button" class="text-xs text-muted-foreground hover:text-foreground" @click="clearFile">
                        Quitar
                    </button>
                </div>
                <p v-if="current?.file_url" class="flex flex-wrap items-center gap-1 text-xs text-muted-foreground">
                    <FileText class="size-3.5" />
                    Ya tiene archivo:
                    <a :href="current.file_url" target="_blank" rel="noopener" class="font-medium text-primary hover:underline">verlo</a>. Si eliges
                    otro, lo reemplaza.
                </p>
                <InputError :message="errors.file" />
            </div>
            <p class="text-xs text-muted-foreground sm:col-span-2">
                {{ document.expires ? 'Los campos con * son obligatorios si llenas este documento.' : 'Escribe el número o adjunta el archivo.' }}
            </p>
        </div>
    </div>
</template>
