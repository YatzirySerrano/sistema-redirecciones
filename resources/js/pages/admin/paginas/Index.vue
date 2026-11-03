<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    CircleAlert,
    ExternalLink,
    FileCheck2,
    Globe,
    Link2,
    Store,
} from '@lucide/vue';
import CampoBusqueda from '@/components/comun/CampoBusqueda.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import Paginacion from '@/components/comun/Paginacion.vue';
import TarjetaIndicador from '@/components/comun/TarjetaIndicador.vue';
import { useFiltros } from '@/composables/useFiltros';
import { numero, relativo } from '@/lib/formato';
import { index } from '@/routes/admin/paginas';
import type { Paginado } from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Páginas de enlaces', href: index() }] },
});

type Fila = {
    id: number;
    negocio: string;
    negocio_id: number;
    negocio_activo: boolean;
    servicio: string | null;
    estado: string;
    estado_etiqueta: string;
    url_publica: string;
    publicado_en: string | null;
    incidencia: string | null;
    incidencia_en: string | null;
    visitas_30d: number;
    clics_30d: number;
    dominios: {
        host: string;
        estado: string;
        estado_etiqueta: string;
        es_principal: boolean;
        ultimo_error: string | null;
        ultima_revision_en: string | null;
    }[];
};

const props = defineProps<{
    filtros: { buscar: string };
    resumen: {
        negocios_con_servicio: number;
        publicadas: number;
        sin_abrir: number;
        dominios_activos: number;
        dominios_con_error: number;
    };
    paginas: Paginado<Fila>;
}>();

const { filtros } = useFiltros(index().url, { ...props.filtros });

const ESTILO_DOMINIO: Record<string, string> = {
    pendiente: 'bg-muted text-muted-foreground',
    dns_verificado: 'bg-primary/10 text-primary',
    activo: 'bg-success-soft text-success',
    error: 'bg-destructive/10 text-destructive',
};
const SERVICIO: Record<string, string> = {
    activa: 'Activo',
    restringida: 'Restringido',
    vencida: 'Vencido',
    suspendida: 'Suspendido',
    cancelada: 'Cancelado',
};
</script>

<template>
    <Head title="Páginas de enlaces" />

    <EncabezadoPagina
        titulo="Páginas de enlaces"
        descripcion="Negocios con el servicio, estado de publicación, dominios propios e incidencias. Por privacidad no se muestran mensajes de visitantes."
        :icono="Link2"
    />

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <TarjetaIndicador
            etiqueta="Negocios con el servicio"
            :valor="numero(resumen.negocios_con_servicio)"
            :icono="Store"
            :detalle="
                resumen.sin_abrir
                    ? `${resumen.sin_abrir} aún no abren el módulo`
                    : undefined
            "
        />
        <TarjetaIndicador
            etiqueta="Páginas publicadas"
            :valor="numero(resumen.publicadas)"
            :icono="FileCheck2"
        />
        <TarjetaIndicador
            etiqueta="Dominios activos"
            :valor="numero(resumen.dominios_activos)"
            :icono="Globe"
        />
        <TarjetaIndicador
            etiqueta="Dominios con error"
            :valor="numero(resumen.dominios_con_error)"
            :icono="CircleAlert"
            :tono="resumen.dominios_con_error ? 'alerta' : 'normal'"
        />
    </div>

    <div class="rounded-xl border bg-card p-3 shadow-xs">
        <CampoBusqueda
            v-model="filtros.buscar"
            placeholder="Buscar negocio o dirección…"
        />
    </div>

    <EstadoVacio
        v-if="paginas.data.length === 0"
        class="rounded-xl border bg-card"
        :icono="Link2"
        titulo="Sin páginas"
        descripcion="Ningún negocio con el servicio ha abierto el módulo todavía."
    />

    <div v-else class="overflow-hidden rounded-xl border bg-card shadow-xs">
        <ul class="divide-y">
            <li
                v-for="p in paginas.data"
                :key="p.id"
                class="grid grid-cols-1 gap-3 p-4 sm:p-5 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_auto] lg:items-start"
            >
                <div class="min-w-0">
                    <p class="flex flex-wrap items-center gap-2 font-medium">
                        {{ p.negocio }}
                        <span
                            v-if="!p.negocio_activo"
                            class="rounded-full bg-destructive/10 px-2 py-0.5 text-[11px] text-destructive"
                            >Negocio suspendido</span
                        >
                    </p>
                    <a
                        :href="p.url_publica"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex max-w-full items-center gap-1 truncate font-mono text-xs text-enlace hover:underline"
                    >
                        {{ p.url_publica }}
                        <ExternalLink class="size-3 shrink-0" />
                    </a>
                    <div class="mt-1.5 flex flex-wrap gap-2 text-xs">
                        <span
                            class="rounded-full px-2 py-0.5"
                            :class="
                                p.estado === 'publicado'
                                    ? 'bg-success-soft text-success'
                                    : 'bg-muted text-muted-foreground'
                            "
                            >{{ p.estado_etiqueta }}</span
                        >
                        <span
                            class="rounded-full bg-muted px-2 py-0.5 text-muted-foreground"
                            >Servicio:
                            {{
                                SERVICIO[p.servicio ?? ''] ?? 'Sin contratar'
                            }}</span
                        >
                        <span
                            v-if="p.publicado_en"
                            class="text-muted-foreground"
                            >Publicada {{ relativo(p.publicado_en) }}</span
                        >
                    </div>
                    <p
                        v-if="p.incidencia"
                        class="mt-2 flex gap-1.5 text-xs text-warning"
                    >
                        <CircleAlert class="size-3.5 shrink-0" />
                        {{ p.incidencia }}
                        <span class="text-muted-foreground"
                            >({{ relativo(p.incidencia_en) }})</span
                        >
                    </p>
                </div>
                <div class="grid grid-cols-1 gap-1.5">
                    <p
                        v-if="p.dominios.length === 0"
                        class="text-xs text-muted-foreground"
                    >
                        Sin dominio propio
                    </p>
                    <div v-for="d in p.dominios" :key="d.host" class="text-xs">
                        <span class="font-mono">{{ d.host }}</span>
                        <span
                            class="ml-1.5 rounded-full px-2 py-0.5"
                            :class="ESTILO_DOMINIO[d.estado]"
                            >{{ d.estado_etiqueta }}</span
                        >
                        <span
                            v-if="d.es_principal"
                            class="ml-1 text-muted-foreground"
                            >· principal</span
                        >
                        <p
                            v-if="d.ultimo_error && d.estado !== 'activo'"
                            class="mt-0.5 text-muted-foreground"
                        >
                            {{ d.ultimo_error }}
                        </p>
                    </div>
                </div>
                <div class="flex gap-6 text-sm lg:text-right">
                    <div>
                        <p class="text-xs text-muted-foreground">
                            Visitas 30 d
                        </p>
                        <p class="font-semibold tabular-nums">
                            {{ numero(p.visitas_30d) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Clics 30 d</p>
                        <p class="font-semibold tabular-nums">
                            {{ numero(p.clics_30d) }}
                        </p>
                    </div>
                </div>
            </li>
        </ul>
        <Paginacion :meta="paginas.meta" nombre="páginas" />
    </div>
</template>
