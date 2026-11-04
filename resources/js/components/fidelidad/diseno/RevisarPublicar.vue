<script setup lang="ts">
import {
    ChevronRight,
    CircleCheck,
    Eye,
    History,
    RotateCcw,
    Send,
    TriangleAlert,
} from '@lucide/vue';
import { ref } from 'vue';
import Ayuda from '@/components/comun/Ayuda.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { fechaHora, relativo } from '@/lib/formato';
import type { AvisoLegibilidad } from './legibilidad';
import type { ClaveHerramienta, Herramienta } from './pasos';
import { herramienta as datosHerramienta } from './pasos';
import type { EstadoDiseno, VersionDiseno } from './tipos';

/**
 * Paso 4: resumen de lo que cambia respecto a lo publicado, avisos de
 * lectura, publicación explicada y versiones anteriores (ver/restaurar).
 */
defineProps<{
    cambios: Herramienta[];
    avisos: AvisoLegibilidad[];
    servidor: EstadoDiseno;
    sucio: boolean;
    puedePublicar: boolean;
    puedeEditar: boolean;
    publicando: boolean;
    destino: string;
    versiones: VersionDiseno[];
    versionVista: number | null;
}>();

const todas = ref(false);

const emit = defineEmits<{
    ir: [herramienta: ClaveHerramienta];
    publicar: [];
    ver: [version: VersionDiseno];
    restaurar: [version: VersionDiseno];
}>();
</script>

<template>
    <div class="space-y-6">
        <!-- Resumen de cambios -->
        <section class="space-y-2" aria-labelledby="r-cambios">
            <h3 id="r-cambios" class="text-sm font-semibold">
                {{
                    servidor.publicado
                        ? 'Cambios respecto a lo que ven tus clientes'
                        : 'Lo que verán tus clientes al publicar'
                }}
            </h3>
            <ul v-if="cambios.length" class="divide-y rounded-lg border">
                <li v-for="c in cambios" :key="c.clave">
                    <button
                        type="button"
                        class="flex min-h-11 w-full items-center gap-3 px-3 py-2 text-left text-sm hover:bg-muted/50"
                        @click="emit('ir', c.clave)"
                    >
                        <component
                            :is="c.icono"
                            class="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <span class="min-w-0 flex-1">
                            <span class="block font-medium">{{
                                c.nombre
                            }}</span>
                            <span class="block text-xs text-muted-foreground">{{
                                c.descripcion
                            }}</span>
                        </span>
                        <ChevronRight
                            class="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </button>
                </li>
            </ul>
            <p
                v-else
                class="flex items-center gap-2 rounded-lg border border-dashed px-3 py-3 text-sm text-muted-foreground"
            >
                <CircleCheck class="size-4 shrink-0" aria-hidden="true" />
                {{
                    servidor.publicado
                        ? 'No hay cambios: tus clientes ya ven este diseño.'
                        : 'Aún no cambias nada. Puedes publicar el diseño tal como está.'
                }}
            </p>
        </section>

        <!-- Avisos de lectura -->
        <section class="space-y-2" aria-labelledby="r-avisos">
            <h3
                id="r-avisos"
                class="flex items-center gap-1.5 text-sm font-semibold"
            >
                Legibilidad
                <Ayuda
                    texto="Revisamos que los textos y sellos se distingan bien de su fondo. Son recomendaciones: puedes publicar de todos modos."
                />
            </h3>
            <ul v-if="avisos.length" class="space-y-2">
                <li v-for="(a, i) in avisos" :key="i">
                    <button
                        type="button"
                        class="flex min-h-11 w-full items-start gap-2 rounded-lg border border-warning/30 bg-warning-soft px-3 py-2 text-left text-xs text-warning hover:brightness-95"
                        @click="emit('ir', a.herramienta)"
                    >
                        <TriangleAlert
                            class="mt-0.5 size-3.5 shrink-0"
                            aria-hidden="true"
                        />
                        <span class="min-w-0 flex-1">
                            {{ a.mensaje }}
                            <span class="mt-0.5 block font-semibold"
                                >Corregir en «{{
                                    datosHerramienta(a.herramienta).nombre
                                }}»</span
                            >
                        </span>
                    </button>
                </li>
            </ul>
            <p
                v-else
                class="flex items-center gap-2 text-sm text-muted-foreground"
            >
                <CircleCheck
                    class="size-4 shrink-0 text-success"
                    aria-hidden="true"
                />
                Los textos y sellos tienen buen contraste.
            </p>
        </section>

        <!-- Publicar -->
        <section
            v-if="puedeEditar"
            class="space-y-3 rounded-xl border bg-muted/30 p-4"
            aria-labelledby="r-publicar"
        >
            <h3
                id="r-publicar"
                class="flex items-center gap-1.5 text-sm font-semibold"
            >
                Publicar
                <Ayuda
                    texto="Tus clientes verán la nueva página la próxima vez que abran su enlace o escaneen su tarjeta. Sus visitas, metas, recompensas y canjes no cambian."
                />
            </h3>
            <p class="text-sm text-muted-foreground">
                {{ destino }} Sus visitas y recompensas no cambian.
                <template v-if="sucio">
                    Al publicar también se guardan tus cambios.</template
                >
            </p>
            <p
                v-if="servidor.publicado_en"
                class="text-xs text-muted-foreground"
            >
                Última publicación: {{ relativo(servidor.publicado_en) }}.
            </p>
            <Button
                class="h-11 w-full sm:w-auto"
                :disabled="!puedePublicar || publicando"
                @click="emit('publicar')"
            >
                <Spinner v-if="publicando" />
                <Send v-else />
                Publicar ahora
            </Button>
        </section>

        <!-- Versiones -->
        <section class="space-y-2" aria-labelledby="r-versiones">
            <h3
                id="r-versiones"
                class="flex items-center gap-1.5 text-sm font-semibold"
            >
                <History class="size-4" aria-hidden="true" />
                Versiones anteriores
                <Ayuda
                    texto="Cada publicación queda guardada. «Ver» la muestra en la vista previa; «Restaurar» la copia a tu borrador (no se publica hasta que lo decidas)."
                />
            </h3>
            <ul v-if="versiones.length" class="divide-y rounded-lg border">
                <li
                    v-for="v in todas ? versiones : versiones.slice(0, 5)"
                    :key="v.id"
                    class="flex flex-wrap items-center justify-between gap-2 px-3 py-2.5"
                >
                    <div class="min-w-0 text-sm">
                        <p class="font-medium">{{ v.motivo }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ fechaHora(v.en) }}
                            <template v-if="v.autor">· {{ v.autor }}</template>
                        </p>
                    </div>
                    <div class="flex gap-1.5">
                        <Button
                            variant="ghost"
                            class="h-10"
                            :aria-pressed="versionVista === v.id"
                            @click="emit('ver', v)"
                        >
                            <Eye /> Ver
                        </Button>
                        <Button
                            v-if="puedeEditar"
                            variant="outline"
                            class="h-10"
                            @click="emit('restaurar', v)"
                        >
                            <RotateCcw /> Restaurar
                        </Button>
                    </div>
                </li>
            </ul>
            <Button
                v-if="versiones.length > 5 && !todas"
                variant="ghost"
                class="h-10 w-full"
                @click="todas = true"
            >
                Mostrar las {{ versiones.length }} versiones
            </Button>
            <p
                v-else
                class="rounded-lg border border-dashed px-4 py-5 text-center text-sm text-muted-foreground"
            >
                Aún no hay publicaciones. Cuando publiques, aparecerán aquí.
            </p>
        </section>
    </div>
</template>
