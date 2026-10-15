<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Ban } from 'lucide-vue-next';

const page = usePage();
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-4 bg-background p-6 text-center">
        <Head title="Acceso suspendido" />
        <Ban class="size-10 text-muted-foreground" />
        <h1 class="text-xl font-medium">Acceso suspendido</h1>
        <p class="max-w-md text-sm text-muted-foreground">
            El acceso de {{ page.props.tenant?.name ?? 'esta empresa' }} al sistema está suspendido temporalmente. Comunícate con el administrador de
            la plataforma.
        </p>
    </div>
</template>
