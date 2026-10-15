<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Acceso de un usuario: o se le envía una invitación por correo para que defina su contraseña,
 * o la contraseña se define aquí. En la edición la contraseña es opcional.
 */
const props = withDefaults(
    defineProps<{
        idPrefix: string;
        invitationLabel: string;
        invitationHint: string;
        passwordRequired?: boolean;
        passwordError?: string;
        passwordConfirmationError?: string;
    }>(),
    {
        passwordRequired: false,
    },
);

const sendInvitation = defineModel<boolean>('sendInvitation', { required: true });
const password = defineModel<string>('password', { required: true });
const passwordConfirmation = defineModel<string>('passwordConfirmation', { required: true });
</script>

<template>
    <div class="space-y-4">
        <Label :for="`${props.idPrefix}_send_invitation`" class="flex items-start gap-3">
            <Checkbox :id="`${props.idPrefix}_send_invitation`" v-model:checked="sendInvitation" class="mt-0.5" />
            <span class="grid gap-1">
                <span>{{ invitationLabel }}</span>
                <span class="text-sm font-normal text-muted-foreground">{{ invitationHint }}</span>
            </span>
        </Label>

        <div v-if="!sendInvitation" class="grid gap-6 md:grid-cols-2">
            <div class="grid gap-2">
                <Label :for="`${props.idPrefix}_password`">{{ passwordRequired ? 'Contraseña' : 'Nueva contraseña (opcional)' }}</Label>
                <Input
                    :id="`${props.idPrefix}_password`"
                    v-model="password"
                    type="password"
                    autocomplete="new-password"
                    :required="passwordRequired"
                />
                <InputError :message="passwordError" />
            </div>
            <div class="grid gap-2">
                <Label :for="`${props.idPrefix}_password_confirmation`">Confirmar contraseña</Label>
                <Input
                    :id="`${props.idPrefix}_password_confirmation`"
                    v-model="passwordConfirmation"
                    type="password"
                    autocomplete="new-password"
                    :required="passwordRequired || password !== ''"
                />
                <InputError :message="passwordConfirmationError" />
            </div>
        </div>
    </div>
</template>
