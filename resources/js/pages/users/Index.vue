<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import RoleBadge from '@/components/RoleBadge.vue';
import ActionButton from '@/components/table/ActionButton.vue';
import DataTable from '@/components/table/DataTable.vue';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Paginated, type SelectOption, type TableColumn, type TableQuery } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed } from 'vue';

interface UserRow {
    id: number;
    name: string;
    username: string;
    email: string | null;
    role: { id: number; name: string; is_protected: boolean } | null;
    invitation_pending: boolean;
    can: { update: boolean; delete: boolean };
}

defineProps<{
    users: Paginated<UserRow>;
    query: TableQuery;
    roles: SelectOption[];
    can: { create: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Usuarios', href: '/users' }];

const columns: TableColumn[] = [
    { key: 'name', label: 'Nombre', sortable: true, primary: true },
    { key: 'username', label: 'Usuario', sortable: true },
    { key: 'email', label: 'Correo', sortable: true },
    { key: 'role', label: 'Rol' },
];

const page = usePage();
const actionError = computed(() => (page.props.errors as Record<string, string>).user);

const { confirm } = useConfirm();

const destroy = async (user: UserRow) => {
    const confirmed = await confirm({
        title: `¿Eliminar a ${user.name}?`,
        text: `El usuario ${user.username} ya no podrá ingresar. No se puede deshacer.`,
        confirmText: 'Eliminar',
        destructive: true,
    });

    if (confirmed) {
        router.delete(route('users.destroy', { user: user.id }), { preserveScroll: true });
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Usuarios" />

        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <Heading title="Usuarios" description="Personas con acceso al sistema de la empresa y su rol." />
                <Button v-if="can.create" as-child class="w-full sm:w-auto">
                    <Link :href="route('users.create')">
                        <Plus />
                        Nuevo usuario
                    </Link>
                </Button>
            </div>

            <InputError :message="actionError" />

            <DataTable
                :rows="users"
                :columns="columns"
                :query="query"
                :url="route('users.index')"
                :only="['users', 'query']"
                :export-urls="{ xlsx: route('users.export', { format: 'xlsx' }), pdf: route('users.export', { format: 'pdf' }) }"
                search-placeholder="Buscar por nombre, usuario o correo"
                empty-text="No hay usuarios que coincidan con la búsqueda."
            >
                <template #filters="{ query: state, apply }">
                    <select
                        :value="state.role ?? ''"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm sm:w-auto"
                        aria-label="Rol"
                        @change="apply({ role: ($event.target as HTMLSelectElement).value || null })"
                    >
                        <option value="">Todos los roles</option>
                        <option v-for="role in roles" :key="role.value" :value="role.value">{{ role.label }}</option>
                    </select>
                </template>

                <template #cell-name="{ row }">
                    <span>{{ row.name }}</span>
                    <span
                        v-if="row.invitation_pending"
                        class="ml-2 whitespace-nowrap rounded-md bg-amber-100 px-1.5 py-0.5 text-xs font-normal text-amber-800 dark:bg-amber-950 dark:text-amber-200"
                    >
                        Invitación pendiente
                    </span>
                </template>
                <template #cell-email="{ row }">
                    <span class="text-muted-foreground">{{ row.email ?? '—' }}</span>
                </template>
                <template #cell-role="{ row }">
                    <RoleBadge :role="row.role" />
                </template>

                <template #actions="{ row, labeled }">
                    <ActionButton
                        v-if="row.can.update"
                        action="edit"
                        :labeled="labeled"
                        :label="`Editar ${row.name}`"
                        :href="route('users.edit', { user: row.id })"
                    />
                    <ActionButton v-if="row.can.delete" action="delete" :labeled="labeled" :label="`Eliminar ${row.name}`" @click="destroy(row)" />
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
