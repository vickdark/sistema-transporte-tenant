<script setup lang="ts">
import RouteStatusBadge from '@/components/RouteStatusBadge.vue';
import ActionButton from '@/components/table/ActionButton.vue';
import { useRouteActivation } from '@/composables/useRouteActivation';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed } from 'vue';

interface RouteDetail {
    id: number;
    name: string;
    start_point: string;
    end_point: string;
    description: string | null;
    type_label: string;
    status: string;
    status_label: string;
    created_at: string | null;
}

const props = defineProps<{
    item: RouteDetail;
    can: { update: boolean; toggle_active: boolean };
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Rutas', href: route('routes.index') },
    { title: props.item.name, href: route('routes.show', { route: props.item.id }) },
]);

const details = computed(() => [
    { label: 'Punto de inicio', value: props.item.start_point },
    { label: 'Punto de fin', value: props.item.end_point },
    { label: 'Tipo de ruta', value: props.item.type_label },
    { label: 'Fecha de creación', value: props.item.created_at },
]);

const { deactivate, activate } = useRouteActivation();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="item.name" />

        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex min-w-0 flex-wrap items-center gap-3">
                    <h2 class="break-words text-xl font-semibold tracking-tight">{{ item.name }}</h2>
                    <RouteStatusBadge :status="item.status" :label="item.status_label" />
                </div>
                <div class="flex flex-wrap gap-2">
                    <ActionButton
                        v-if="can.update"
                        action="edit"
                        labeled
                        :label="`Editar ${item.name}`"
                        :href="route('routes.edit', { route: item.id })"
                    />
                    <template v-if="can.toggle_active">
                        <ActionButton
                            v-if="item.status !== 'inactive'"
                            action="deactivate"
                            labeled
                            :label="`Inhabilitar ${item.name}`"
                            @click="deactivate(item)"
                        />
                        <ActionButton v-else action="activate" labeled :label="`Habilitar ${item.name}`" @click="activate(item)" />
                    </template>
                </div>
            </div>

            <dl class="grid grid-cols-1 gap-x-8 gap-y-4 rounded-xl border border-sidebar-border/70 p-4 min-[420px]:grid-cols-2 sm:p-6 xl:grid-cols-4">
                <div v-for="detail in details" :key="detail.label" class="min-w-0">
                    <dt class="text-xs uppercase text-muted-foreground">{{ detail.label }}</dt>
                    <dd class="mt-1 break-words text-sm">{{ detail.value || '—' }}</dd>
                </div>
                <div class="min-w-0 min-[420px]:col-span-2 xl:col-span-4">
                    <dt class="text-xs uppercase text-muted-foreground">Descripción</dt>
                    <dd class="mt-1 whitespace-pre-line break-words text-sm">{{ item.description || '—' }}</dd>
                </div>
            </dl>

            <div>
                <Link :href="route('routes.index')" class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="size-4" />
                    Regresar a rutas
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
