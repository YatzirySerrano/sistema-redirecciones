<script setup lang="ts">
import { cn } from "@/lib/utils";

/**
 * Pregunta «¿Cuántos QR lleva impresos?» con dos tarjetas ilustradas
 * (radios nativos: accesibles con las flechas del teclado).
 */
const modelo = defineModel<number>({ required: true });

const props = withDefaults(
    defineProps<{
        /** El producto también lleva chip NFC (combinado). */
        conNfc?: boolean;
        nombre?: string;
        deshabilitado?: boolean;
    }>(),
    { conNfc: false, nombre: "cantidad_qr", deshabilitado: false },
);

const OPCIONES = [
    {
        valor: 1,
        titulo: "1 QR",
        texto: () =>
            props.conNfc
                ? "Un código QR impreso además del chip NFC."
                : "Un solo código QR con su enlace permanente.",
    },
    {
        valor: 2,
        titulo: "2 QR",
        texto: () =>
            props.conNfc
                ? "Dos QR y el NFC, cada uno con su propio destino y estadísticas."
                : "Dos QR en la misma impresión, cada uno con su propio destino, diseño y estadísticas.",
    },
] as const;
</script>

<template>
    <div class="grid grid-cols-2 gap-3" role="radiogroup">
        <label
            v-for="opcion in OPCIONES"
            :key="opcion.valor"
            :class="
                cn(
                    'relative flex cursor-pointer flex-col items-center gap-2 rounded-xl border bg-background p-4 text-center transition hover:border-primary/40 has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/30',
                    modelo === opcion.valor &&
                        'border-primary bg-accent/50 ring-1 ring-primary',
                    deshabilitado && 'pointer-events-none opacity-60',
                )
            "
        >
            <input
                v-model="modelo"
                type="radio"
                :name="nombre"
                :value="opcion.valor"
                :disabled="deshabilitado"
                class="sr-only"
            />
            <!-- Ilustración: tarjeta con uno o dos QR (y el chip si lleva NFC). -->
            <svg
                viewBox="0 0 120 72"
                class="h-14 w-24 text-primary"
                aria-hidden="true"
            >
                <rect
                    x="2"
                    y="2"
                    width="116"
                    height="68"
                    rx="9"
                    class="fill-muted stroke-border"
                    stroke-width="2"
                />
                <g
                    v-for="(x, i) in opcion.valor === 1 ? [44] : [20, 68]"
                    :key="i"
                    fill="currentColor"
                >
                    <rect
                        :x="x"
                        y="18"
                        width="32"
                        height="32"
                        rx="3"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="3"
                    />
                    <rect :x="x + 6" y="24" width="8" height="8" rx="1" />
                    <rect :x="x + 18" y="24" width="8" height="8" rx="1" />
                    <rect :x="x + 6" y="36" width="8" height="8" rx="1" />
                    <rect :x="x + 19" y="37" width="5" height="5" />
                    <text
                        v-if="opcion.valor === 2"
                        :x="x + 16"
                        y="62"
                        text-anchor="middle"
                        font-size="9"
                        font-weight="700"
                        class="fill-muted-foreground"
                    >
                        {{ i + 1 }}
                    </text>
                </g>
                <g v-if="conNfc" class="text-muted-foreground">
                    <path
                        d="M104 12 a8 8 0 0 1 0 12 M108 9 a12 12 0 0 1 0 18"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                    />
                </g>
            </svg>
            <span class="text-sm font-semibold">{{ opcion.titulo }}</span>
            <span class="text-xs leading-relaxed text-muted-foreground">{{
                opcion.texto()
            }}</span>
        </label>
    </div>
</template>
