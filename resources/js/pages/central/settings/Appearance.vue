<script setup lang="ts">
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import CentralSettingsLayout from '@/layouts/central/CentralSettingsLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Apariencia', href: route('central.settings.appearance', undefined, false) }];
</script>

<template>
    <CentralSettingsLayout :breadcrumbs="breadcrumbs">
        <Head title="Apariencia" />

        <div class="space-y-6">
            <HeadingSmall title="Apariencia" description="Elige el tema claro, oscuro o el del sistema" />
            <AppearanceTabs />
        </div>
    </CentralSettingsLayout>
</template>
