<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    titulo: string;
    descripcion?: string;
    icono?: Component;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center px-6 py-12 text-center"
    >
        <span
            v-if="icono"
            class="mb-4 flex size-12 items-center justify-center rounded-2xl bg-accent text-accent-foreground"
            aria-hidden="true"
        >
            <component :is="icono" class="size-6" />
        </span>
        <h3 class="text-sm font-semibold">{{ titulo }}</h3>
        <p
            v-if="descripcion"
            class="mt-1 max-w-sm text-sm text-muted-foreground"
        >
            {{ descripcion }}
        </p>
        <div v-if="$slots.default" class="mt-5">
            <slot />
        </div>
    </div>
</template>
