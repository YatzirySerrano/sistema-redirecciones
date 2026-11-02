<script setup lang="ts">
import { computed } from 'vue';
import MensajeValidacion from '@/components/formularios/MensajeValidacion.vue';
import { estadoConfirmacion } from '@/lib/validacion';

/** Indica en vivo si la confirmación coincide con la contraseña. */
const props = defineProps<{ contrasena: string; confirmacion: string }>();

const estado = computed(() =>
    estadoConfirmacion(props.contrasena ?? '', props.confirmacion ?? ''),
);
</script>

<template>
    <MensajeValidacion :mensaje="estado.mensaje" :valido="estado.valido" />
</template>
