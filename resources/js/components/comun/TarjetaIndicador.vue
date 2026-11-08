<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';
import type { Component } from 'vue';
import Ayuda from '@/components/comun/Ayuda.vue';
import Chispa from '@/components/graficas/Chispa.vue';
import IndicadorTendencia from '@/components/graficas/IndicadorTendencia.vue';
import { cn } from '@/lib/utils';

/**
 * Indicador numérico. Cada indicador responde a una pregunta concreta
 * (etiqueta), aclara el dato (detalle) y, si hay dónde actuar, enlaza al
 * listado correspondiente. Opcionalmente muestra la comparación con el
 * periodo anterior y una minigráfica de tendencia.
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
        comparacion?: {
            actual: number;
            anterior: number;
            mejor?: 'arriba' | 'abajo' | 'neutral';
            contexto?: string;
        };
        chispa?: number[];
        chispaColor?: string;
    }>(),
    { tono: 'normal' },
);
</script>

<template>
    <div
        :class="
            cn(
                'group relative flex flex-col rounded-xl border bg-card p-4 shadow-xs sm:p-5',
                href && 'tarjeta-interactiva',
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
                        'flex size-8 shrink-0 items-center justify-center rounded-lg transition-transform duration-200 ease-out group-hover:scale-110 group-hover:-rotate-3',
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
        <IndicadorTendencia
            v-if="comparacion"
            class="mt-1.5"
            :actual="comparacion.actual"
            :anterior="comparacion.anterior"
            :mejor="comparacion.mejor"
            :contexto="comparacion.contexto"
        />
        <p
            v-if="detalle"
            class="mt-1 text-xs text-pretty text-muted-foreground"
        >
            {{ detalle }}
        </p>
        <slot />
        <div v-if="chispa && chispa.length > 1" class="mt-3">
            <Chispa :valores="chispa" :color="chispaColor" />
        </div>
        <Link
            v-if="href"
            :href="href"
            class="mt-auto inline-flex items-center gap-1 pt-3 text-xs font-medium text-enlace after:absolute after:inset-0 after:content-['']"
        >
            Ver detalle
            <ArrowUpRight
                class="size-3.5 transition-transform duration-200 ease-out group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                aria-hidden="true"
            />
        </Link>
    </div>
</template>
