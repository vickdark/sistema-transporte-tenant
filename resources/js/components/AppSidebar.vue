<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { usePermissions } from '@/composables/usePermissions';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { LayoutGrid, ShieldCheck, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const { can } = usePermissions();

const allNavItems: NavItem[] = [
    { title: 'Inicio', href: '/dashboard', icon: LayoutGrid },
    { title: 'Usuarios', href: '/users', icon: Users, permission: 'users.view' },
    { title: 'Roles y permisos', href: '/roles', icon: ShieldCheck, permission: 'roles.view' },
];

/** El menú solo muestra lo que el rol del usuario permite. */
const mainNavItems = computed(() => allNavItems.filter((item) => !item.permission || can(item.permission)));
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
