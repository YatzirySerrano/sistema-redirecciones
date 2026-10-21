<script setup lang="ts">
import { Nfc, QrCode, ScanLine } from '@lucide/vue';
import type { Component } from 'vue';
import { cn } from '@/lib/utils';
import type { Opcion } from '@/types';

/**
 * Tarjetas de selección del tipo de producto (radios nativos: accesibles
 * por teclado con las flechas).
 */
const modelo = defineModel<string>({ required: true });
defineProps<{ tipos: Opcion[] }>();

const DETALLE: Record<string, { icono: Component; texto: string }> = {
    qr: { icono: QrCode, texto: 'Código impreso (mesa, mostrador, empaque).' },
    nfc: {
        icono: Nfc,
        texto: 'Tarjeta o etiqueta que se lee al acercar el teléfono.',
    },
    combinado: {
        icono: ScanLine,
        texto: 'QR y NFC comparten el mismo enlace: no se puede distinguir qué canal originó cada visita. Si necesitas estadísticas por canal, registra dos productos.',
    },
};
</script>

<template>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3" role="radiogroup">
        <label
            v-for="tipo in tipos"
            :key="tipo.value"
            :class="
                cn(
                    'relative flex cursor-pointer flex-col gap-2 rounded-xl border bg-background p-4 transition hover:border-primary/40 has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/30',
                    modelo === tipo.value &&
                        'border-primary bg-accent/50 ring-1 ring-primary',
                )
            "
        >
            <input
                v-model="modelo"
                type="radio"
                name="tipo"
                :value="tipo.value"
                class="sr-only"
            />
            <span class="flex items-center gap-2 text-sm font-semibold">
                <component
                    :is="DETALLE[tipo.value]?.icono ?? ScanLine"
                    class="size-4 text-primary"
                    aria-hidden="true"
                />
                {{ tipo.label }}
            </span>
            <span class="text-xs leading-relaxed text-muted-foreground">{{
                DETALLE[tipo.value]?.texto
            }}</span>
        </label>
    </div>
</template>
