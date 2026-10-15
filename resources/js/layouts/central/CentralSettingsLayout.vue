<script setup lang="ts">
import CentralLayout from '@/layouts/CentralLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { BreadcrumbItemType, NavItem } from '@/types';

defineProps<{
    breadcrumbs: BreadcrumbItemType[];
}>();

const items: NavItem[] = [
    { title: 'Contraseña', href: route('central.settings.password.edit', undefined, false) },
    { title: 'Apariencia', href: route('central.settings.appearance', undefined, false) },
];
</script>

<template>
    <CentralLayout :breadcrumbs="breadcrumbs">
        <SettingsLayout :items="items" title="Configuración" description="Administra la seguridad y la apariencia de tu cuenta">
            <slot />
        </SettingsLayout>
    </CentralLayout>
</template>
