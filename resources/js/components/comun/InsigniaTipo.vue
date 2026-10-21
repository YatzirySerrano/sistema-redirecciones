<script setup lang="ts">
import { Nfc, QrCode, ScanLine } from '@lucide/vue';
import { computed } from 'vue';
import type { TipoProducto } from '@/types';

const props = defineProps<{ tipo: TipoProducto | string; etiqueta?: string }>();

const icono = computed(() =>
    props.tipo === 'nfc' ? Nfc : props.tipo === 'qr' ? QrCode : ScanLine,
);
const texto = computed(
    () =>
        props.etiqueta ??
        (props.tipo === 'nfc'
            ? 'NFC'
            : props.tipo === 'qr'
              ? 'QR'
              : 'QR + NFC'),
);
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-md border bg-background px-1.5 py-0.5 text-xs font-medium whitespace-nowrap text-foreground/80"
    >
        <component
            :is="icono"
            class="size-3.5 text-primary"
            aria-hidden="true"
        />
        {{ texto }}
    </span>
</template>
