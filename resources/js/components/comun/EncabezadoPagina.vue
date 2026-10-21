<script setup lang="ts">
defineProps<{
    titulo: string;
    descripcion?: string;
}>();
</script>

<template>
    <div
        class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
    >
        <div class="min-w-0 space-y-1">
            <slot name="antes" />
            <h1 class="text-2xl font-semibold tracking-tight text-balance">
                {{ titulo }}
            </h1>
            <p
                v-if="descripcion"
                class="max-w-2xl text-sm text-muted-foreground"
            >
                {{ descripcion }}
            </p>
            <slot name="detalle" />
        </div>
        <div v-if="$slots.acciones" class="flex flex-wrap items-center gap-2">
            <slot name="acciones" />
        </div>
    </div>
</template>
