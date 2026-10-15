<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { usePermissions } from '@/composables/usePermissions';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Building2, Contact, Handshake, LayoutGrid, Route as RouteIcon, ShieldCheck, Truck, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const { can } = usePermissions();

const allNavItems: NavItem[] = [
    { title: 'Inicio', href: '/dashboard', icon: LayoutGrid },
    { title: 'Clientes', href: '/clients', icon: Contact, permission: 'clients.view' },
    { title: 'Rutas', href: '/routes', icon: RouteIcon, permission: 'routes.view' },
    { title: 'Aliados', href: '/allies', icon: Handshake, permission: 'allies.view' },
    { title: 'Proveedores', href: '/suppliers', icon: Truck, permission: 'suppliers.view' },
    { title: 'Usuarios', href: '/users', icon: Users, permission: 'users.view' },
    { title: 'Roles y permisos', href: '/roles', icon: ShieldCheck, permission: 'roles.view' },
    { title: 'Configuración de la empresa', href: '/company', icon: Building2, permission: 'company.manage' },
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
