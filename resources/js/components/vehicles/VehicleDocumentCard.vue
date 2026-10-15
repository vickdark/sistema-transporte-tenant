<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import DocumentStatusBadge from '@/components/vehicles/DocumentStatusBadge.vue';
import { type VehicleDocumentSlot } from '@/types/vehicles';
import { useForm } from '@inertiajs/vue3';
import { ChevronDown, FileText, History, LoaderCircle, Pencil, Plus, RefreshCw } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * Un tipo de documento del vehículo: su documento vigente y su historial.
 *
 * - Registrar / Corregir: guarda el vigente sin dejar rastro (un archivo nuevo reemplaza al anterior).
 * - Renovar: el vigente pasa al historial con su archivo y se registra el nuevo.
 */
const props = defineProps<{
    vehicleId: number;
    document: VehicleDocumentSlot;
    canUpdate: boolean;
    maxFileMb: number;
}>();

const mode = ref<'save' | 'renew' | null>(null);
const showHistory = ref(false);

const form = useForm({
    number: '',
    issued_on: '',
    expires_on: '',
    file: null as File | null,
});

const labels = computed(() => props.document.field_labels);
const current = computed(() => props.document.current);

const open = (selected: 'save' | 'renew') => {
    form.clearErrors();
    form.number = selected === 'save' ? (current.value?.number ?? '') : '';
    form.issued_on = selected === 'save' ? (current.value?.issued_on ?? '') : '';
    form.expires_on = selected === 'save' ? (current.value?.expires_on ?? '') : '';
    form.file = null;
    mode.value = selected;
};

const dialogTitle = computed(() => {
    if (mode.value === 'renew') {
        return `Renovar ${props.document.label}`;
    }

    return current.value ? `Corregir ${props.document.label}` : `Registrar ${props.document.label}`;
});

const submit = () => {
    const url =
        mode.value === 'renew'
            ? route('vehicles.documents.renew', { vehicle: props.vehicleId, type: props.document.type })
            : route('vehicles.documents.store', { vehicle: props.vehicleId, type: props.document.type });

    form.post(url, {
        preserveScroll: true,
        onSuccess: () => (mode.value = null),
    });
};

const onFile = (event: Event) => {
    form.file = (event.target as HTMLInputElement).files?.[0] ?? null;
};

const formatDate = (date: string | null) => (date ? new Date(`${date}T00:00:00`).toLocaleDateString('es-CO', { dateStyle: 'medium' }) : '—');
</script>

<template>
    <article class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-4">
        <div class="flex items-start justify-between gap-3">
            <h4 class="text-sm font-medium">{{ document.label }}</h4>
            <DocumentStatusBadge v-if="current" :status="current.status" />
            <span
                v-else
                class="whitespace-nowrap rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300"
            >
                Sin registrar
            </span>
        </div>

        <dl v-if="current" class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
            <div class="col-span-2 min-w-0">
                <dt class="text-xs text-muted-foreground">{{ labels.number }}</dt>
                <dd class="break-words">{{ current.number || '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">{{ labels.issued_on }}</dt>
                <dd>{{ formatDate(current.issued_on) }}</dd>
            </div>
            <div v-if="document.expires">
                <dt class="text-xs text-muted-foreground">{{ labels.expires_on }}</dt>
                <dd>{{ formatDate(current.expires_on) }}</dd>
            </div>
        </dl>

        <div class="mt-auto flex flex-wrap gap-2">
            <a
                v-if="current?.file_url"
                :href="current.file_url"
                target="_blank"
                rel="noopener"
                class="inline-flex h-8 items-center gap-1.5 rounded-md bg-slate-100 px-3 text-xs font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            >
                <FileText class="size-4" />
                Ver archivo
            </a>
            <template v-if="canUpdate">
                <button
                    type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded-md bg-blue-50 px-3 text-xs font-medium text-blue-700 hover:bg-blue-100 dark:bg-blue-950 dark:text-blue-300 dark:hover:bg-blue-900"
                    @click="open('save')"
                >
                    <component :is="current ? Pencil : Plus" class="size-4" />
                    {{ current ? 'Corregir' : 'Registrar' }}
                </button>
                <button
                    v-if="current"
                    type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded-md bg-emerald-50 px-3 text-xs font-medium text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950 dark:text-emerald-300 dark:hover:bg-emerald-900"
                    @click="open('renew')"
                >
                    <RefreshCw class="size-4" />
                    Renovar
                </button>
            </template>
            <button
                v-if="document.history.length > 0"
                type="button"
                class="inline-flex h-8 items-center gap-1.5 rounded-md px-2 text-xs font-medium text-muted-foreground hover:bg-muted"
                :aria-expanded="showHistory"
                @click="showHistory = !showHistory"
            >
                <History class="size-4" />
                Historial ({{ document.history.length }})
                <ChevronDown class="size-4 transition-transform" :class="{ 'rotate-180': showHistory }" />
            </button>
        </div>

        <ul v-if="showHistory" class="divide-y divide-sidebar-border/70 rounded-lg border border-sidebar-border/70 text-xs">
            <li v-for="old in document.history" :key="old.id" class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                <div class="min-w-0">
                    <p class="break-words font-medium">{{ old.number || 'Sin número' }}</p>
                    <p class="text-muted-foreground">
                        <template v-if="document.expires">Venció {{ formatDate(old.expires_on) }} · </template>Reemplazado
                        {{ formatDate(old.replaced_at) }}
                    </p>
                </div>
                <a
                    v-if="old.file_url"
                    :href="old.file_url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-1 font-medium text-primary hover:underline"
                >
                    <FileText class="size-3.5" />
                    Archivo
                </a>
            </li>
        </ul>

        <Dialog :open="mode !== null" @update:open="(value) => !value && (mode = null)">
            <DialogContent>
                <form class="flex flex-col gap-5" @submit.prevent="submit">
                    <DialogHeader>
                        <DialogTitle>{{ dialogTitle }}</DialogTitle>
                        <DialogDescription v-if="mode === 'renew'">
                            El documento actual pasará al historial con su archivo. Registra los datos del nuevo.
                        </DialogDescription>
                        <DialogDescription v-else-if="current">Corrige los datos del documento vigente; no queda en el historial.</DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2 sm:col-span-2">
                            <Label :for="`${document.type}-number`">{{ labels.number }}{{ document.expires ? '' : ' *' }}</Label>
                            <Input :id="`${document.type}-number`" v-model="form.number" maxlength="50" />
                            <InputError :message="form.errors.number" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`${document.type}-issued`">{{ labels.issued_on }}</Label>
                            <Input :id="`${document.type}-issued`" v-model="form.issued_on" type="date" />
                            <InputError :message="form.errors.issued_on" />
                        </div>
                        <div v-if="document.expires" class="grid gap-2">
                            <Label :for="`${document.type}-expires`">{{ labels.expires_on }} *</Label>
                            <Input :id="`${document.type}-expires`" v-model="form.expires_on" type="date" required />
                            <InputError :message="form.errors.expires_on" />
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <Label :for="`${document.type}-file`">Archivo (PDF o imagen, máximo {{ maxFileMb }} MB)</Label>
                            <input
                                :id="`${document.type}-file`"
                                type="file"
                                accept="application/pdf,image/png,image/jpeg"
                                class="text-sm file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-sm file:font-medium"
                                @change="onFile"
                            />
                            <p v-if="mode === 'save' && current?.file_url" class="text-xs text-muted-foreground">
                                Si eliges uno, reemplaza el archivo actual.
                            </p>
                            <InputError :message="form.errors.file" />
                        </div>
                    </div>

                    <div v-if="form.progress" class="h-1.5 overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full bg-primary transition-all" :style="{ width: `${form.progress.percentage}%` }" />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button type="button" variant="outline" @click="mode = null">Cancelar</Button>
                        <Button type="submit" :disabled="form.processing">
                            <LoaderCircle v-if="form.processing" class="animate-spin" />
                            {{ mode === 'renew' ? 'Renovar' : 'Guardar' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </article>
</template>
