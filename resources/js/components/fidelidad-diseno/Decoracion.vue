<script setup lang="ts">
import { useId } from 'vue';
import type { ClaveDecoracion } from './tipos';

/**
 * Patrones decorativos originales (SVG en línea, sin archivos externos).
 * Se dibujan detrás del contenido y no reciben eventos ni lectores.
 */
defineProps<{
    tipo: ClaveDecoracion;
    color: string;
    /** 0 a 60 (porcentaje). */
    opacidad: number;
}>();

const id = `deco-${useId()}`;
</script>

<template>
    <svg
        v-if="tipo !== 'ninguna' && opacidad > 0"
        class="pointer-events-none absolute inset-0 h-full w-full"
        aria-hidden="true"
        focusable="false"
        :style="{ opacity: opacidad / 100 }"
    >
        <defs>
            <pattern
                v-if="tipo === 'puntos'"
                :id="id"
                width="18"
                height="18"
                patternUnits="userSpaceOnUse"
            >
                <circle cx="3" cy="3" r="1.6" :fill="color" />
                <circle cx="12" cy="12" r="1.6" :fill="color" />
            </pattern>
            <pattern
                v-else-if="tipo === 'ondas'"
                :id="id"
                width="48"
                height="20"
                patternUnits="userSpaceOnUse"
            >
                <path
                    d="M0 10 Q 12 0 24 10 T 48 10"
                    fill="none"
                    :stroke="color"
                    stroke-width="1.6"
                />
            </pattern>
            <pattern
                v-else-if="tipo === 'granos'"
                :id="id"
                width="56"
                height="56"
                patternUnits="userSpaceOnUse"
            >
                <g :fill="color">
                    <g transform="translate(14 14) rotate(-30)">
                        <ellipse rx="6" ry="9" />
                        <path
                            d="M0 -8 C -3 -3 3 3 0 8"
                            stroke="white"
                            stroke-opacity=".45"
                            stroke-width="1.4"
                            fill="none"
                        />
                    </g>
                    <g transform="translate(42 40) rotate(35)">
                        <ellipse rx="5" ry="8" />
                        <path
                            d="M0 -7 C -3 -2 3 2 0 7"
                            stroke="white"
                            stroke-opacity=".45"
                            stroke-width="1.3"
                            fill="none"
                        />
                    </g>
                </g>
            </pattern>
            <pattern
                v-else-if="tipo === 'hojas'"
                :id="id"
                width="64"
                height="64"
                patternUnits="userSpaceOnUse"
            >
                <g fill="none" :stroke="color" stroke-width="1.4">
                    <path
                        d="M10 40 C 10 22 24 12 34 12 C 34 28 24 40 10 40 Z"
                    />
                    <path d="M10 40 L 30 16" />
                    <path
                        d="M44 60 C 44 48 54 42 60 42 C 60 52 54 60 44 60 Z"
                    />
                </g>
            </pattern>
            <pattern
                v-else-if="tipo === 'rayas'"
                :id="id"
                width="14"
                height="14"
                patternUnits="userSpaceOnUse"
                patternTransform="rotate(45)"
            >
                <rect width="5" height="14" :fill="color" />
            </pattern>
            <pattern
                v-else-if="tipo === 'confeti'"
                :id="id"
                width="70"
                height="70"
                patternUnits="userSpaceOnUse"
            >
                <g :fill="color">
                    <rect
                        x="8"
                        y="10"
                        width="8"
                        height="3"
                        rx="1.5"
                        transform="rotate(25 12 11)"
                    />
                    <circle cx="44" cy="14" r="2.5" />
                    <rect
                        x="52"
                        y="44"
                        width="9"
                        height="3"
                        rx="1.5"
                        transform="rotate(-35 56 45)"
                    />
                    <circle cx="20" cy="52" r="2" />
                    <path d="M34 34 l3 6 l-6 0 z" />
                </g>
            </pattern>
            <pattern
                v-else-if="tipo === 'arcos'"
                :id="id"
                width="80"
                height="80"
                patternUnits="userSpaceOnUse"
            >
                <g fill="none" :stroke="color" stroke-width="1.3">
                    <circle cx="80" cy="0" r="22" />
                    <circle cx="80" cy="0" r="38" />
                    <circle cx="80" cy="0" r="54" />
                    <circle cx="0" cy="80" r="22" />
                    <circle cx="0" cy="80" r="38" />
                </g>
            </pattern>
            <pattern
                v-else-if="tipo === 'burbujas'"
                :id="id"
                width="90"
                height="90"
                patternUnits="userSpaceOnUse"
            >
                <g fill="none" :stroke="color" stroke-width="1.4">
                    <circle cx="20" cy="22" r="12" />
                    <circle cx="64" cy="58" r="18" />
                    <circle cx="70" cy="14" r="5" />
                    <circle cx="22" cy="72" r="6" />
                </g>
            </pattern>
            <pattern
                v-else-if="tipo === 'diamantes'"
                :id="id"
                width="28"
                height="28"
                patternUnits="userSpaceOnUse"
            >
                <path
                    d="M14 3 L25 14 L14 25 L3 14 Z"
                    fill="none"
                    :stroke="color"
                    stroke-width="1.2"
                />
            </pattern>
        </defs>
        <rect width="100%" height="100%" :fill="`url(#${id})`" />
    </svg>
</template>
