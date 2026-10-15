<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useHttp } from '@inertiajs/vue3';
import { CircleCheck, CircleX, LoaderCircle, PlugZap } from 'lucide-vue-next';
import { computed, ref } from 'vue';

export interface LegacyFilesForm {
    files_host: string;
    files_port: number | '';
    files_username: string;
    files_password: string;
    files_private_key: string;
    files_root: string;
}

/**
 * Acceso SFTP a los archivos del legacy (documentos, fotos…). Funciona igual desde el servidor que
 * desde otro equipo. Al editar, la contraseña y la llave vacías conservan las guardadas.
 */
const props = defineProps<{
    errors: Partial<Record<keyof LegacyFilesForm, string>>;
    /** Secretos ya guardados (al editar); nunca llegan sus valores. */
    saved?: { has_password: boolean; has_private_key: boolean } | null;
    /** Fuente que se edita: la prueba usa sus secretos guardados si no se escriben otros. */
    sourceId?: number;
}>();

const form = defineModel<LegacyFilesForm>({ required: true });

/** "Probar conexión": prueba el acceso con lo escrito, sin guardar nada. */
const http = useHttp<Record<string, never>, { message: string }>();
const result = ref<{ ok: boolean; message: string } | null>(null);

const test = async () => {
    result.value = null;

    try {
        const response = await http
            .transform(() => ({
                files_host: form.value.files_host,
                files_port: form.value.files_port,
                files_username: form.value.files_username,
                files_password: form.value.files_password,
                files_private_key: form.value.files_private_key,
                files_root: form.value.files_root,
                source_id: props.sourceId ?? null,
            }))
            .post(route('central.migrations.sources.test-files'));
        result.value = { ok: true, message: response.message };
    } catch {
        const errors = Object.values(http.errors as Record<string, string>);
        result.value = { ok: false, message: errors[0] ?? 'No se pudo probar la conexión.' };
    }
};

const canTest = computed(() => form.value.files_host.trim() !== '' && !http.processing);
</script>

<template>
    <fieldset class="flex flex-col gap-4 rounded-xl border border-sidebar-border/70 p-4">
        <legend class="px-1 text-sm font-medium">Archivos del legacy por SFTP (opcional)</legend>
        <p class="text-sm text-muted-foreground">
            Acceso al servidor del legacy para copiar sus documentos y fotos, desde aquí o desde cualquier equipo. Solo se lee: nunca se escribe ni se
            borra allá. Sin servidor se migran los datos pero no los archivos.
        </p>

        <div class="grid gap-6 md:grid-cols-3">
            <div class="grid gap-2 md:col-span-2">
                <Label for="files_host">Servidor</Label>
                <Input id="files_host" v-model="form.files_host" placeholder="transporteskemuel.mambacode.dev o su IP" autocomplete="off" />
                <InputError :message="errors.files_host" />
            </div>
            <div class="grid gap-2">
                <Label for="files_port">Puerto</Label>
                <Input id="files_port" v-model="form.files_port" type="number" min="1" max="65535" />
                <InputError :message="errors.files_port" />
            </div>
            <div class="grid gap-2">
                <Label for="files_username">Usuario</Label>
                <Input id="files_username" v-model="form.files_username" autocomplete="off" />
                <InputError :message="errors.files_username" />
            </div>
            <div class="grid gap-2 md:col-span-2">
                <Label for="files_password">Contraseña</Label>
                <Input
                    id="files_password"
                    v-model="form.files_password"
                    type="password"
                    autocomplete="new-password"
                    :placeholder="saved?.has_password ? 'Guardada · escribe otra para cambiarla' : ''"
                />
                <InputError :message="errors.files_password" />
            </div>
            <div class="grid gap-2 md:col-span-3">
                <Label for="files_private_key">Llave privada (en lugar de la contraseña)</Label>
                <textarea
                    id="files_private_key"
                    v-model="form.files_private_key"
                    rows="3"
                    spellcheck="false"
                    :placeholder="saved?.has_private_key ? 'Guardada · pega otra para cambiarla' : '-----BEGIN OPENSSH PRIVATE KEY-----'"
                    class="rounded-md border border-input bg-transparent px-3 py-2 font-mono text-xs shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                />
                <InputError :message="errors.files_private_key" />
            </div>
            <div class="grid gap-2 md:col-span-3">
                <Label for="files_root">Carpeta del legacy en el servidor</Label>
                <Input id="files_root" v-model="form.files_root" placeholder="/var/www/transporteskemuel.mambacode.dev" autocomplete="off" />
                <p class="text-xs text-muted-foreground">
                    Ruta completa de la carpeta que contiene <code>uploads/</code> (también sirve la de <code>uploads/</code>). Las rutas guardadas en
                    la base legacy, como <code>uploads/vehiculos/ABC123/foto.jpg</code>, se buscan ahí. Al guardar se prueba la conexión.
                </p>
                <InputError :message="errors.files_root" />
            </div>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:gap-3">
            <button
                type="button"
                class="inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-md border border-input px-3 text-sm font-medium hover:bg-muted disabled:opacity-60"
                :disabled="!canTest"
                @click="test"
            >
                <LoaderCircle v-if="http.processing" class="size-4 animate-spin" />
                <PlugZap v-else class="size-4" />
                {{ http.processing ? 'Probando…' : 'Probar conexión' }}
            </button>
            <p
                v-if="result"
                class="flex items-start gap-1.5 text-sm"
                :class="result.ok ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
                role="status"
            >
                <CircleCheck v-if="result.ok" class="mt-0.5 size-4 shrink-0" />
                <CircleX v-else class="mt-0.5 size-4 shrink-0" />
                <span class="break-words">{{ result.message }}</span>
            </p>
        </div>
    </fieldset>
</template>
