<script setup lang="ts">
import { ChartNoAxesColumn, Info } from '@lucide/vue';
import type { Component } from 'vue';
import Ayuda from '@/components/comun/Ayuda.vue';
import { cn } from '@/lib/utils';

/**
 * Contenedor de una gráfica: título, explicación breve de qué representa,
 * ayuda opcional, nota de cobertura de los datos y estado vacío propio
 * (nunca se dibuja una gráfica engañosa sin datos).
 */
withDefaults(
    defineProps<{
        titulo: string;
        descripcion?: string;
        ayuda?: string;
        nota?: string | null;
        vacio?: boolean;
        mensajeVacio?: string;
        iconoVacio?: Component;
        class?: string;
    }>(),
    { mensajeVacio: 'Sin interacciones en este periodo.' },
);
</script>

<template>
    <section
        :class="
            cn(
                'flex min-w-0 flex-col rounded-xl border bg-card text-card-foreground shadow-xs',
                $props.class,
            )
        "
    >
        <header
            class="flex flex-wrap items-start justify-between gap-3 px-4 pt-4 sm:px-5 sm:pt-5"
        >
            <div class="min-w-0 flex-[1_1_16rem]">
                <h2 class="flex items-center gap-1 text-sm font-semibold">
                    {{ titulo }}
                    <Ayuda v-if="ayuda" :texto="ayuda" />
                </h2>
                <p
                    v-if="descripcion"
                    class="mt-0.5 text-xs text-pretty text-muted-foreground"
                >
                    {{ descripcion }}
                </p>
            </div>
            <div
                v-if="$slots.acciones"
                class="flex flex-wrap items-center gap-2"
            >
                <slot name="acciones" />
            </div>
        </header>
        <div class="flex-1 px-4 pt-4 pb-4 sm:px-5 sm:pb-5">
            <div
                v-if="vacio"
                class="flex h-full min-h-32 flex-col items-center justify-center gap-2 rounded-lg border border-dashed px-4 py-8 text-center"
            >
                <component
                    :is="iconoVacio ?? ChartNoAxesColumn"
                    class="size-5 text-muted-foreground/70"
                    aria-hidden="true"
                />
                <p class="max-w-xs text-sm text-pretty text-muted-foreground">
                    {{ mensajeVacio }}
                </p>
            </div>
            <slot v-else />
        </div>
        <p
            v-if="nota"
            class="flex gap-1.5 border-t px-4 py-2.5 text-[11px] text-pretty text-muted-foreground sm:px-5"
        >
            <Info class="mt-px size-3.5 shrink-0" aria-hidden="true" />
            <span>{{ nota }}</span>
        </p>
    </section>
</template>
