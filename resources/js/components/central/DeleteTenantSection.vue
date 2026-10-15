<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/composables/useConfirm';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, Trash2 } from 'lucide-vue-next';

const props = defineProps<{
    tenantId: string;
    tenantName: string;
    subdomain: string;
}>();

const { confirmByTyping } = useConfirm();
const form = useForm({ confirmation: '' });

const destroy = async () => {
    const confirmed = await confirmByTyping({
        title: `¿Eliminar ${props.tenantName}?`,
        text: 'Se borrarán de forma definitiva la empresa, su subdominio y su base de datos con todos sus datos. No se puede deshacer.',
        confirmText: 'Eliminar definitivamente',
        expected: props.subdomain,
    });

    if (!confirmed) {
        return;
    }

    form.confirmation = props.subdomain;
    form.delete(route('central.tenants.destroy', { tenant: props.tenantId }));
};
</script>

<template>
    <section class="max-w-3xl space-y-4">
        <HeadingSmall title="Eliminar empresa" />
        <div class="space-y-4 rounded-xl border border-red-200 bg-red-50 p-6 dark:border-red-900/50 dark:bg-red-950/30">
            <p class="text-sm text-red-700 dark:text-red-200">
                Se borra la empresa y su base de datos completa (usuarios y toda su información). No se puede deshacer. Si solo quieres bloquear el
                acceso, usa <strong>Suspender</strong>.
            </p>

            <Button variant="destructive" :disabled="form.processing" @click="destroy">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                <Trash2 v-else />
                Eliminar empresa
            </Button>
            <InputError :message="form.errors.confirmation" />
        </div>
    </section>
</template>
