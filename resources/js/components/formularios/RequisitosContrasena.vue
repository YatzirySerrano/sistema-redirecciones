<script setup lang="ts">
import { Check, Circle } from '@lucide/vue';
import { computed } from 'vue';
import MensajeValidacion from '@/components/formularios/MensajeValidacion.vue';
import { estadoConfirmacion, requisitosContrasena } from '@/lib/validacion';

/**
 * Requisitos de la contraseña en español, evaluados mientras se escribe a
 * partir de las reglas reales del servidor (passwordRules). Si se pasa
 * `confirmacion`, indica también si ambas coinciden.
 */
const props = defineProps<{
    valor: string;
    reglas?: string | null;
    confirmacion?: string | null;
}>();

const requisitos = computed(() =>
    requisitosContrasena(props.valor ?? '', props.reglas),
);
const coincidencia = computed(() =>
    props.confirmacion === undefined || props.confirmacion === null
        ? null
        : estadoConfirmacion(props.valor ?? '', props.confirmacion),
);
</script>

<template>
    <div class="grid gap-1.5" aria-live="polite">
        <ul v-if="valor" class="grid gap-1 text-xs">
            <li
                v-for="requisito in requisitos"
                :key="requisito.clave"
                class="flex items-start gap-1.5"
                :class="
                    requisito.cumplido
                        ? 'text-success'
                        : 'text-muted-foreground'
                "
            >
                <Check
                    v-if="requisito.cumplido"
                    class="mt-px size-3.5 shrink-0"
                    aria-hidden="true"
                />
                <Circle
                    v-else
                    class="mt-px size-3.5 shrink-0"
                    aria-hidden="true"
                />
                <span>
                    {{ requisito.texto }}
                    <span class="sr-only">{{
                        requisito.cumplido ? '(cumplido)' : '(pendiente)'
                    }}</span>
                </span>
            </li>
        </ul>
        <p v-else class="text-xs text-muted-foreground">
            {{ requisitos[0]?.texto }}
        </p>
        <MensajeValidacion
            v-if="coincidencia"
            :mensaje="coincidencia.mensaje"
            :valido="coincidencia.valido"
        />
    </div>
</template>
