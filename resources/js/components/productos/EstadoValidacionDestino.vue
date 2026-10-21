<script setup lang="ts">
import {
    CircleAlert,
    CircleCheck,
    ExternalLink,
    LockKeyhole,
} from '@lucide/vue';
import { Spinner } from '@/components/ui/spinner';
import type { EstadoValidacionDestino } from '@/composables/useValidacionDestino';

/**
 * Resultado de la comprobación en vivo de una URL de destino. Solo muestra
 * «✓» cuando el servidor confirmó que la dirección se aceptará al guardar.
 */
withDefaults(
    defineProps<{
        resultado: EstadoValidacionDestino;
        /** Texto cuando el campo está vacío (null para no mostrar nada). */
        textoVacio?: string | null;
        /** Muestra el enlace «Abrir para comprobar». */
        conEnlace?: boolean;
    }>(),
    { textoVacio: null, conEnlace: true },
);
</script>

<template>
    <div aria-live="polite" class="min-h-5 text-xs">
        <p
            v-if="resultado.estado === 'vacia' && textoVacio"
            class="text-muted-foreground"
        >
            {{ textoVacio }}
        </p>
        <p
            v-else-if="resultado.estado === 'comprobando'"
            class="flex items-center gap-1.5 text-muted-foreground"
        >
            <Spinner class="size-3.5" /> Comprobando la dirección…
        </p>
        <p
            v-else-if="resultado.estado === 'invalida'"
            class="flex items-center gap-1.5 text-destructive"
        >
            <CircleAlert class="size-3.5 shrink-0" aria-hidden="true" />
            {{ resultado.motivo }}
        </p>
        <p
            v-else-if="resultado.estado === 'sin_comprobar'"
            class="flex items-center gap-1.5 text-warning"
        >
            <CircleAlert class="size-3.5 shrink-0" aria-hidden="true" />
            {{ resultado.motivo }} Se validará al guardar.
        </p>
        <div
            v-else-if="resultado.estado === 'valida'"
            class="flex flex-wrap items-center gap-x-3 gap-y-1 text-muted-foreground"
        >
            <span class="flex items-center gap-1.5 text-success">
                <CircleCheck class="size-3.5" aria-hidden="true" /> Lleva a
                <strong class="font-semibold">{{ resultado.dominio }}</strong>
            </span>
            <span
                v-if="!resultado.segura"
                class="flex items-center gap-1 text-warning"
            >
                <CircleAlert class="size-3.5" aria-hidden="true" /> Sin conexión
                segura (http)
            </span>
            <span v-else class="flex items-center gap-1"
                ><LockKeyhole class="size-3.5" aria-hidden="true" /> Conexión
                segura</span
            >
            <a
                v-if="conEnlace"
                :href="resultado.url"
                target="_blank"
                rel="noopener noreferrer nofollow"
                class="inline-flex items-center gap-1 font-medium text-primary hover:underline"
            >
                Abrir para comprobar <ExternalLink class="size-3" />
            </a>
        </div>
    </div>
</template>
