<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    baseDomain: string;
    error?: string;
}>();

const subdomain = defineModel<string>({ required: true });
</script>

<template>
    <div class="grid gap-2">
        <Label for="subdomain">Subdominio</Label>
        <div class="flex items-center gap-2">
            <Input id="subdomain" v-model="subdomain" required class="min-w-0 max-w-xs flex-1" placeholder="kemuel" />
            <span class="shrink-0 text-sm text-muted-foreground">.{{ baseDomain }}</span>
        </div>
        <InputError :message="error" />
    </div>
</template>
