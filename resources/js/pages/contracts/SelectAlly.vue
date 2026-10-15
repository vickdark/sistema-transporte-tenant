<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SearchSelectOption } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Handshake, LoaderCircle } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    searchUrl: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Contratos de aliados', href: '/ally-contracts' }];

const ally = ref<SearchSelectOption | null>(null);
const form = useForm({ ally_id: null as number | string | null });

const submit = () => {
    form.ally_id = ally.value?.value ?? null;
    form.post(route('ally-contracts.store-ally'));
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Elegir aliado" />

        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <Heading title="Contratos de aliados" description="Elige el aliado con el que vas a trabajar. Podrás cambiarlo en cualquier momento." />

            <form class="flex max-w-xl flex-col gap-4 rounded-xl border border-sidebar-border/70 p-4 sm:p-6" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="ally">Aliado</Label>
                    <SearchSelect id="ally" v-model="ally" :search-url="searchUrl" placeholder="Escribe el nombre o el NIT del aliado" />
                    <p class="text-xs text-muted-foreground">Haz clic en el campo para ver la lista o escribe para filtrarla.</p>
                </div>

                <Button type="submit" :disabled="!ally || form.processing" class="w-full sm:w-auto sm:self-start">
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    <Handshake v-else />
                    Trabajar con este aliado
                </Button>
            </form>
        </div>
    </AppLayout>
</template>
