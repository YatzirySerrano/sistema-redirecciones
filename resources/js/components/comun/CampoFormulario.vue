<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';

/** Etiqueta + control + ayuda + error, con el espaciado del sistema. */
defineProps<{
    etiqueta: string;
    para?: string;
    error?: string;
    ayuda?: string;
    opcional?: boolean;
}>();
</script>

<template>
    <div class="grid gap-2">
        <Label :for="para" class="text-sm font-medium">
            {{ etiqueta }}
            <span v-if="opcional" class="font-normal text-muted-foreground"
                >(opcional)</span
            >
        </Label>
        <slot />
        <p v-if="ayuda && !error" class="text-xs text-muted-foreground">
            {{ ayuda }}
        </p>
        <InputError :message="error" />
    </div>
</template>
