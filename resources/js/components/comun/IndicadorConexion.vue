<script setup lang="ts">
import { WifiOff } from '@lucide/vue';
import { enLinea } from '@/lib/pwa';

/**
 * Aviso persistente cuando el dispositivo pierde la conexión. No simula
 * guardados: indica que las operaciones esperan a la red.
 */
</script>

<template>
    <div
        v-if="!enLinea"
        class="sticky top-0 z-50 flex items-center justify-center gap-2 bg-warning px-4 py-2 text-center text-xs font-medium text-white"
        role="status"
        aria-live="polite"
    >
        <WifiOff class="size-4 shrink-0" aria-hidden="true" />
        Sin conexión. No se guardará ningún cambio, visita ni canje hasta que
        vuelvas a estar en línea.
    </div>
</template>
