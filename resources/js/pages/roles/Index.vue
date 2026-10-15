<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import RoleBadge from '@/components/RoleBadge.vue';
import ActionButton from '@/components/table/ActionButton.vue';
import DataTable from '@/components/table/DataTable.vue';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Paginated, type TableColumn, type TableQuery } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed } from 'vue';

interface RoleRow {
    id: number;
    name: string;
    description: string | null;
    is_protected: boolean;
    users_count: number;
    permissions_count: number;
    can: { update: boolean; delete: boolean };
}

defineProps<{
    roles: Paginated<RoleRow>;
    query: TableQuery;
    totalPermissions: number;
    can: { create: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Roles y permisos', href: '/roles' }];

const columns: TableColumn[] = [
    { key: 'name', label: 'Rol', sortable: true, primary: true },
    { key: 'permissions_count', label: 'Permisos', sortable: true, align: 'right' },
    { key: 'users_count', label: 'Usuarios', sortable: true, align: 'right' },
];

const page = usePage();
const actionError = computed(() => (page.props.errors as Record<string, string>).role);

const { confirm } = useConfirm();

const destroy = async (role: RoleRow) => {
    const confirmed = await confirm({
        title: `¿Eliminar el rol ${role.name}?`,
        text: 'Se borra el rol y su configuración de permisos.',
        confirmText: 'Eliminar',
        destructive: true,
    });

    if (confirmed) {
        router.delete(route('roles.destroy', { role: role.id }), { preserveScroll: true });
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Roles y permisos" />

        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <Heading title="Roles y permisos" description="Cada usuario tiene un rol; el rol define lo que puede hacer en el sistema." />
                <Button v-if="can.create" as-child class="w-full sm:w-auto">
                    <Link :href="route('roles.create')">
                        <Plus />
                        Nuevo rol
                    </Link>
                </Button>
            </div>

            <InputError :message="actionError" />

            <DataTable
                :rows="roles"
                :columns="columns"
                :query="query"
                :url="route('roles.index')"
                :only="['roles', 'query']"
                :export-urls="{ xlsx: route('roles.export', { format: 'xlsx' }), pdf: route('roles.export', { format: 'pdf' }) }"
                search-placeholder="Buscar por nombre o descripción"
                empty-text="No hay roles que coincidan con la búsqueda."
            >
                <template #cell-name="{ row }">
                    <RoleBadge :role="{ id: row.id, name: row.name, is_protected: row.is_protected }" />
                    <p class="mt-1 text-xs font-normal text-muted-foreground">
                        {{ row.is_protected ? 'Tiene todos los permisos y no se puede modificar.' : row.description }}
                    </p>
                </template>
                <template #cell-permissions_count="{ row }">{{ row.permissions_count }} / {{ totalPermissions }}</template>

                <template #actions="{ row, labeled }">
                    <ActionButton
                        v-if="row.can.update"
                        action="edit"
                        :labeled="labeled"
                        :label="`Editar ${row.name}`"
                        :href="route('roles.edit', { role: row.id })"
                    />
                    <ActionButton v-if="row.can.delete" action="delete" :labeled="labeled" :label="`Eliminar ${row.name}`" @click="destroy(row)" />
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
