<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Insignia de estado. El color nunca va solo: siempre se acompaña de texto
 * y de un punto indicador.
 */
const props = defineProps<{
    estado: string;
    etiqueta?: string;
}>();

const ETIQUETAS: Record<string, string> = {
    activo: 'Activo',
    suspendido: 'Suspendido',
    pendiente: 'Pendiente',
    listo: 'Listo',
    entregado: 'Entregado',
    redirigiendo: 'Redirigiendo',
    sin_destino: 'Sin destino',
    producto_suspendido: 'Suspendido',
    negocio_suspendido: 'Negocio suspendido',
};

const tono = computed(() => {
    switch (props.estado) {
        case 'activo':
        case 'entregado':
        case 'redirigiendo':
            return 'bg-success-soft text-success';
        case 'suspendido':
        case 'producto_suspendido':
        case 'negocio_suspendido':
            return 'bg-destructive/10 text-destructive';
        case 'pendiente':
        case 'sin_destino':
            return 'bg-warning-soft text-warning';
        default:
            return 'bg-accent text-accent-foreground';
    }
});
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                tono,
            )
        "
    >
        <span class="size-1.5 rounded-full bg-current" aria-hidden="true" />
        {{ etiqueta ?? ETIQUETAS[estado] ?? estado }}
    </span>
</template>
