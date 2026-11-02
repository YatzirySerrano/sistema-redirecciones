<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';
import type { Component } from 'vue';
import Ayuda from '@/components/comun/Ayuda.vue';
import { cn } from '@/lib/utils';

/**
 * Indicador numérico. Cada indicador responde a una pregunta concreta
 * (etiqueta), aclara el dato (detalle) y, si hay dónde actuar, enlaza al
 * listado correspondiente.
 */
withDefaults(
    defineProps<{
        etiqueta: string;
        valor: string | number;
        icono?: Component;
        detalle?: string;
        ayuda?: string;
        href?: NonNullable<InertiaLinkProps['href']>;
        tono?: 'normal' | 'alerta' | 'exito';
    }>(),
    { tono: 'normal' },
);
</script>

<template>
    <div
        :class="
            cn(
                'group relative flex flex-col rounded-xl border bg-card p-4 shadow-xs transition sm:p-5',
                href && 'hover:border-primary/40 hover:shadow-sm',
                tono === 'alerta' && 'border-warning/30 bg-warning-soft/40',
            )
        "
    >
        <div class="flex items-start justify-between gap-3">
            <div class="flex min-w-0 items-center gap-1">
                <p
                    class="line-clamp-2 text-xs font-medium text-muted-foreground sm:text-sm"
                >
                    {{ etiqueta }}
                </p>
                <Ayuda v-if="ayuda" :texto="ayuda" class="relative z-10" />
            </div>
            <span
                v-if="icono"
                :class="
                    cn(
                        'flex size-8 shrink-0 items-center justify-center rounded-lg',
                        tono === 'alerta'
                            ? 'bg-warning-soft text-warning'
                            : tono === 'exito'
                              ? 'bg-success-soft text-success'
                              : 'bg-primary/10 text-primary',
                    )
                "
                aria-hidden="true"
            >
                <component :is="icono" class="size-4" />
            </span>
        </div>
        <p
            class="mt-2 text-2xl font-semibold tracking-tight tabular-nums sm:text-[28px]"
        >
            {{ valor }}
        </p>
        <p
            v-if="detalle"
            class="mt-1 text-xs text-pretty text-muted-foreground"
        >
            {{ detalle }}
        </p>
        <Link
            v-if="href"
            :href="href"
            class="mt-auto inline-flex items-center gap-1 pt-3 text-xs font-medium text-enlace after:absolute after:inset-0 after:content-['']"
        >
            Ver detalle <ArrowUpRight class="size-3.5" aria-hidden="true" />
        </Link>
    </div>
</template>
