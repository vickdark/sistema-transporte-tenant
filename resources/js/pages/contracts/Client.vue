<script setup lang="ts">
import ActiveAllyBanner from '@/components/contracts/ActiveAllyBanner.vue';
import ContractStatusBadge from '@/components/contracts/ContractStatusBadge.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import ActionButton from '@/components/table/ActionButton.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { type ContractData, type ContractModule, clientUrl, formatDate } from '@/types/contracts';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Lock, Plus } from 'lucide-vue-next';
import { computed } from 'vue';

interface ContractRow extends ContractData {
    status: 'current' | 'upcoming' | 'expired';
    can_update: boolean;
    edit_url: string;
}

const props = defineProps<{
    module: ContractModule;
    client: {
        id: number;
        name: string;
        type_label: string;
        identification: string;
        phone: string;
        address: string;
        can_contract: boolean;
    };
    contracts: ContractRow[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: props.module.title, href: props.module.urls.index },
    { title: props.client.name, href: clientUrl(props.module, props.client.id) },
]);

const createUrl = computed(() => `${props.module.urls.create}?client=${props.client.id}`);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Contratos de ${client.name}`" />

        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <ActiveAllyBanner :module="module" />

            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <h2 class="break-words text-xl font-semibold tracking-tight">{{ client.name }}</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ client.type_label }} · {{ client.identification }} · {{ client.phone }} · {{ client.address }}
                    </p>
                </div>
                <Button v-if="module.can_create && client.can_contract" as-child class="w-full sm:w-auto">
                    <Link :href="createUrl">
                        <Plus />
                        Nuevo contrato
                    </Link>
                </Button>
            </div>

            <section class="flex flex-col gap-4">
                <HeadingSmall title="Contratos" description="Solo se editan los vigentes y los futuros; los vencidos quedan de solo lectura." />

                <p
                    v-if="contracts.length === 0"
                    class="rounded-xl border border-dashed border-sidebar-border p-6 text-center text-sm text-muted-foreground"
                >
                    Este cliente todavía no tiene contratos.
                </p>

                <article
                    v-for="contract in contracts"
                    :key="contract.id"
                    class="flex flex-col gap-4 rounded-xl border p-4"
                    :class="contract.status === 'current' ? 'border-green-300 dark:border-green-900' : 'border-sidebar-border/70'"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <h3 class="font-semibold tabular-nums">Contrato {{ contract.number }}</h3>
                            <ContractStatusBadge :status="contract.status" />
                        </div>
                        <ActionButton
                            v-if="contract.can_update"
                            action="edit"
                            labeled
                            :label="`Editar contrato ${contract.number}`"
                            :href="contract.edit_url"
                        />
                        <span v-else class="inline-flex items-center gap-1 text-xs text-muted-foreground">
                            <Lock class="size-3.5" />
                            Solo lectura
                        </span>
                    </div>

                    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm min-[420px]:grid-cols-2 lg:grid-cols-4">
                        <div class="min-[420px]:col-span-2">
                            <dt class="text-xs text-muted-foreground">Objeto del contrato</dt>
                            <dd>{{ contract.purpose_label }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Inicio</dt>
                            <dd class="tabular-nums">{{ formatDate(contract.starts_on) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Fin</dt>
                            <dd class="tabular-nums">{{ formatDate(contract.ends_on) }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-xs text-muted-foreground">Responsable</dt>
                            <dd class="break-words">{{ contract.responsible_name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Identificación</dt>
                            <dd class="tabular-nums">{{ contract.responsible_identification ?? '—' }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-xs text-muted-foreground">Correo</dt>
                            <dd class="break-words">{{ contract.responsible_email ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Teléfono</dt>
                            <dd>{{ contract.responsible_phone ?? '—' }}</dd>
                        </div>
                    </dl>
                </article>
            </section>

            <div>
                <Link :href="module.urls.index" class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="size-4" />
                    Regresar a {{ module.title.toLowerCase() }}
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
