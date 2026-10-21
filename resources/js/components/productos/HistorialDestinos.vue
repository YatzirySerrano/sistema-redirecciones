<script setup lang="ts">
import { ArrowRight, History } from '@lucide/vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import { dominio, fechaHora, relativo } from '@/lib/formato';
import type { CambioDestino } from '@/types';

withDefaults(
    defineProps<{ cambios: CambioDestino[]; mostrarProducto?: boolean }>(),
    {
        mostrarProducto: false,
    },
);
</script>

<template>
    <EstadoVacio
        v-if="cambios.length === 0"
        :icono="History"
        titulo="Sin cambios registrados"
        descripcion="Aquí aparecerá cada modificación del destino."
    />
    <ol v-else class="relative space-y-5 border-l pl-5">
        <li v-for="cambio in cambios" :key="cambio.id" class="relative">
            <span
                class="absolute top-1.5 -left-[25px] size-2.5 rounded-full border-2 border-card bg-primary"
                aria-hidden="true"
            />
            <div
                class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5 text-xs text-muted-foreground"
            >
                <span>
                    <span class="font-medium text-foreground">{{
                        cambio.autor
                    }}</span>
                    <template v-if="mostrarProducto && cambio.producto">
                        · {{ cambio.producto.nombre }}</template
                    >
                </span>
                <time
                    :datetime="cambio.created_at"
                    :title="fechaHora(cambio.created_at)"
                    >{{ relativo(cambio.created_at) }}</time
                >
            </div>
            <div
                class="mt-1 flex min-w-0 flex-wrap items-center gap-1.5 text-sm"
            >
                <span
                    class="max-w-full truncate text-muted-foreground line-through decoration-muted-foreground/40"
                    :title="cambio.url_anterior ?? ''"
                >
                    {{
                        cambio.url_anterior
                            ? dominio(cambio.url_anterior)
                            : 'Sin destino'
                    }}
                </span>
                <ArrowRight
                    class="size-3.5 shrink-0 text-muted-foreground"
                    aria-label="cambió a"
                />
                <a
                    v-if="cambio.url_nueva"
                    :href="cambio.url_nueva"
                    target="_blank"
                    rel="noopener noreferrer nofollow"
                    class="max-w-full truncate font-medium hover:text-primary hover:underline"
                    :title="cambio.url_nueva"
                    >{{ cambio.url_nueva }}</a
                >
                <span v-else class="font-medium">Sin destino</span>
            </div>
        </li>
    </ol>
</template>
