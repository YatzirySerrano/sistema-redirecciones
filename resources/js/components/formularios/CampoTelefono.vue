<script setup lang="ts">
import { computed } from 'vue';
import MensajeValidacion from '@/components/formularios/MensajeValidacion.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { estadoTelefono } from '@/lib/validacion';

/**
 * Teléfono mexicano de 10 dígitos con retroalimentación mientras se escribe.
 * Acepta pegar números con espacios, guiones, paréntesis o +52; el servidor
 * guarda solo los 10 dígitos (GuardarNegocioRequest).
 */
const modelo = defineModel<string | null>({ required: true });

const props = withDefaults(
    defineProps<{
        id?: string;
        /** Nombre del campo para formularios no controlados (<Form>). */
        name?: string;
        error?: string;
        placeholder?: string;
        requerido?: boolean;
    }>(),
    { id: 'telefono', placeholder: '55 1234 5678', requerido: false },
);

const PERMITIDOS = /[^0-9+()\-.\s]/g;

const valor = computed({
    get: () => modelo.value ?? '',
    // Se descartan letras y símbolos no válidos al escribir o pegar.
    set: (nuevo: string) => (modelo.value = nuevo.replace(PERMITIDOS, '')),
});

const estado = computed(() => estadoTelefono(valor.value));
const idMensaje = computed(() => `${props.id}-estado`);
</script>

<template>
    <div class="grid gap-1.5">
        <Input
            :id="id"
            :name="name"
            v-model="valor"
            type="tel"
            inputmode="tel"
            maxlength="20"
            autocomplete="tel-national"
            :placeholder="placeholder"
            :required="requerido"
            :aria-invalid="!!error || undefined"
            :aria-describedby="estado.mensaje ? idMensaje : undefined"
        />
        <MensajeValidacion
            v-if="!error"
            :id="idMensaje"
            :mensaje="estado.mensaje"
            :valido="estado.valido"
        />
        <InputError :message="error" />
    </div>
</template>
