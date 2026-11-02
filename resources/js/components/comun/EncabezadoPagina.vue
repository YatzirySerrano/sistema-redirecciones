<script setup lang="ts">
import type { Component } from 'vue';
import Ayuda from '@/components/comun/Ayuda.vue';

/**
 * Encabezado de cada pantalla: qué es (título), para qué sirve
 * (descripción breve) y acciones principales. La ayuda opcional explica
 * conceptos sin llenar la pantalla de texto.
 */
defineProps<{
    titulo: string;
    descripcion?: string;
    ayuda?: string;
    icono?: Component;
}>();
</script>

<template>
    <div
        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
    >
        <div class="flex min-w-0 gap-3.5">
            <span
                v-if="icono"
                class="mt-0.5 hidden size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/15 sm:flex"
                aria-hidden="true"
            >
                <component :is="icono" class="size-5" />
            </span>
            <div class="min-w-0 space-y-1">
                <slot name="antes" />
                <div class="flex items-center gap-1.5">
                    <h1
                        class="text-xl font-semibold tracking-tight text-balance sm:text-2xl"
                    >
                        {{ titulo }}
                    </h1>
                    <Ayuda v-if="ayuda" :texto="ayuda" lado="bottom" />
                </div>
                <p
                    v-if="descripcion"
                    class="max-w-2xl text-sm text-pretty text-muted-foreground"
                >
                    {{ descripcion }}
                </p>
                <slot name="detalle" />
            </div>
        </div>
        <div
            v-if="$slots.acciones"
            class="flex shrink-0 flex-wrap items-center gap-2 [&>*]:max-sm:flex-1"
        >
            <slot name="acciones" />
        </div>
    </div>
</template>
