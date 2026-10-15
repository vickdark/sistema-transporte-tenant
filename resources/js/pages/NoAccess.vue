<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ShieldOff } from 'lucide-vue-next';

const page = usePage();
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-4 bg-background p-6 text-center">
        <Head title="Sin acceso asignado" />
        <ShieldOff class="size-10 text-muted-foreground" />
        <h1 class="text-xl font-medium">Sin acceso asignado</h1>
        <p class="max-w-md text-sm text-muted-foreground">
            Hola, {{ page.props.auth.user?.name }}. Tu cuenta en {{ page.props.tenant?.name ?? 'esta empresa' }} todavía no tiene un rol asignado.
            Pídele a un administrador de la empresa que te asigne uno.
        </p>
        <div class="flex gap-2">
            <Button variant="outline" as-child>
                <Link :href="route('profile.edit')">Mi perfil</Link>
            </Button>
            <Button variant="ghost" as-child>
                <Link :href="route('logout')" method="post" as="button">Cerrar sesión</Link>
            </Button>
        </div>
    </div>
</template>
