<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    etiqueta: string;
    valor: string | number;
    icono?: Component;
    detalle?: string;
}>();
</script>

<template>
    <div class="rounded-xl border bg-card p-5 shadow-xs">
        <div class="flex items-start justify-between gap-3">
            <p class="text-sm text-muted-foreground">{{ etiqueta }}</p>
            <span
                v-if="icono"
                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent text-accent-foreground"
                aria-hidden="true"
            >
                <component :is="icono" class="size-[18px]" />
            </span>
        </div>
        <p class="mt-1 text-2xl font-semibold tracking-tight tabular-nums">
            {{ valor }}
        </p>
        <p v-if="detalle" class="mt-1 text-xs text-muted-foreground">
            {{ detalle }}
        </p>
    </div>
</template>
