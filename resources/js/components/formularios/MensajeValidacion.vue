<script setup lang="ts">
import { CircleAlert, CircleCheck } from '@lucide/vue';

/** Mensaje breve de validación en vivo (correcto o pendiente). */
defineProps<{ mensaje: string; valido: boolean; id?: string }>();
</script>

<template>
    <p
        v-if="mensaje"
        :id="id"
        class="flex items-start gap-1.5 text-xs"
        :class="valido ? 'text-success' : 'text-muted-foreground'"
        aria-live="polite"
    >
        <CircleCheck v-if="valido" class="mt-px size-3.5 shrink-0" />
        <CircleAlert v-else class="mt-px size-3.5 shrink-0 text-warning" />
        <span>{{ mensaje }}</span>
    </p>
</template>
