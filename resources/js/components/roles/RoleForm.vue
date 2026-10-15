<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type InertiaForm, Link } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

export interface PermissionGroup {
    module: string;
    permissions: { name: string; label: string }[];
}

export interface RoleFormData {
    name: string;
    description: string;
    permissions: string[];
}

defineProps<{
    permissionGroups: PermissionGroup[];
    submitLabel: string;
}>();

const form = defineModel<InertiaForm<RoleFormData>>('form', { required: true });
const emit = defineEmits<{ submit: [] }>();

const isGranted = (permission: string): boolean => form.value.permissions.includes(permission);

const toggle = (permission: string, granted: boolean) => {
    form.value.permissions = granted
        ? [...new Set([...form.value.permissions, permission])]
        : form.value.permissions.filter((name) => name !== permission);
};

const isModuleGranted = (group: PermissionGroup): boolean => group.permissions.every((permission) => isGranted(permission.name));

const toggleModule = (group: PermissionGroup, granted: boolean) => {
    group.permissions.forEach((permission) => toggle(permission.name, granted));
};
</script>

<template>
    <form class="max-w-3xl space-y-8" @submit.prevent="emit('submit')">
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">Nombre del rol</Label>
                <Input id="name" v-model="form.name" required autofocus />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="description">Descripción (opcional)</Label>
                <Input id="description" v-model="form.description" placeholder="Qué hace quien tiene este rol" />
                <InputError :message="form.errors.description" />
            </div>
        </div>

        <section class="space-y-3">
            <div>
                <h3 class="text-sm font-medium">Permisos</h3>
                <p class="text-sm text-muted-foreground">Marca lo que puede hacer quien tenga este rol.</p>
            </div>

            <div class="divide-y divide-sidebar-border/70 rounded-xl border border-sidebar-border/70">
                <div v-for="group in permissionGroups" :key="group.module" class="flex flex-wrap items-center gap-x-6 gap-y-3 px-4 py-3">
                    <label class="flex w-full items-center gap-2 text-sm font-medium sm:w-48">
                        <input
                            type="checkbox"
                            class="size-4 rounded border-input"
                            :checked="isModuleGranted(group)"
                            @change="toggleModule(group, ($event.target as HTMLInputElement).checked)"
                        />
                        {{ group.module }}
                    </label>
                    <label v-for="permission in group.permissions" :key="permission.name" class="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            class="size-4 rounded border-input"
                            :checked="isGranted(permission.name)"
                            @change="toggle(permission.name, ($event.target as HTMLInputElement).checked)"
                        />
                        {{ permission.label }}
                    </label>
                </div>
            </div>
            <InputError :message="form.errors.permissions" />
        </section>

        <div class="flex items-center gap-3">
            <Button type="submit" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                {{ submitLabel }}
            </Button>
            <Button variant="ghost" as-child>
                <Link :href="route('roles.index')">Cancelar</Link>
            </Button>
        </div>
    </form>
</template>
