<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    ChartColumnBig,
    CircleCheck,
    MousePointerClick,
    Nfc,
    PackageOpen,
    TrendingUp,
} from '@lucide/vue';
import { computed } from 'vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import InsigniaTipo from '@/components/comun/InsigniaTipo.vue';
import Panel from '@/components/comun/Panel.vue';
import TarjetaIndicador from '@/components/comun/TarjetaIndicador.vue';
import GraficaArea from '@/components/graficas/GraficaArea.vue';
import HistorialDestinos from '@/components/productos/HistorialDestinos.vue';
import { Button } from '@/components/ui/button';
import { dominio, numero } from '@/lib/formato';
import { inicio } from '@/routes/cliente';
import { index as estadisticas } from '@/routes/cliente/estadisticas';
import { index as productosIndex, show } from '@/routes/cliente/productos';
import type { CambioDestino, Coleccion, Producto, PuntoSerie } from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Inicio', href: inicio() }] },
});

const props = defineProps<{
    negocio: { nombre_comercial: string };
    indicadores: {
        productos: number;
        productos_activos: number;
        accesos_totales: number;
        accesos_30_dias: number;
    };
    serie: PuntoSerie[] | null;
    productos: Coleccion<Producto>;
    actividad: Coleccion<CambioDestino>;
}>();

const page = usePage();
const nombre = computed(() => page.props.auth.user.name.split(' ')[0]);
const verEstadisticas = computed(
    () => page.props.auth.permisos.ver_estadisticas,
);
const saludo = computed(() => {
    const h = new Date().getHours();

    return h < 12 ? 'Buenos días' : h < 19 ? 'Buenas tardes' : 'Buenas noches';
});
</script>

<template>
    <Head title="Inicio" />

    <EncabezadoPagina
        :titulo="`${saludo}, ${nombre}`"
        :descripcion="`Administra los QR y tarjetas NFC de ${props.negocio.nombre_comercial}.`"
    >
        <template #acciones>
            <Button as-child>
                <Link :href="productosIndex()"
                    >Ver mis productos <ArrowRight
                /></Link>
            </Button>
        </template>
    </EncabezadoPagina>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <TarjetaIndicador
            etiqueta="Productos adquiridos"
            :valor="numero(indicadores.productos)"
            :icono="Nfc"
        />
        <TarjetaIndicador
            etiqueta="Productos activos"
            :valor="numero(indicadores.productos_activos)"
            :icono="CircleCheck"
        />
        <TarjetaIndicador
            etiqueta="Accesos registrados"
            :valor="numero(indicadores.accesos_totales)"
            :icono="MousePointerClick"
            detalle="Desde que tienes tus productos"
        />
        <TarjetaIndicador
            etiqueta="Accesos (30 días)"
            :valor="numero(indicadores.accesos_30_dias)"
            :icono="TrendingUp"
        />
    </div>

    <Panel
        v-if="serie"
        titulo="Accesos de los últimos 30 días"
        descripcion="Escaneos y lecturas de todos tus productos."
    >
        <template v-if="verEstadisticas" #acciones>
            <Button variant="ghost" size="sm" as-child>
                <Link :href="estadisticas()"
                    ><ChartColumnBig /> Ver estadísticas</Link
                >
            </Button>
        </template>
        <GraficaArea :serie="serie" etiqueta="Accesos" />
    </Panel>

    <div
        class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]"
    >
        <Panel titulo="Tus productos más usados" sin-relleno>
            <template #acciones>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="productosIndex()">Ver todos</Link>
                </Button>
            </template>
            <EstadoVacio
                v-if="productos.data.length === 0"
                :icono="PackageOpen"
                titulo="Aún no tienes productos asignados"
                descripcion="Cuando DASTI registre tus QR o tarjetas NFC aparecerán aquí."
            />
            <ul v-else class="divide-y" role="list">
                <li v-for="p in productos.data" :key="p.id">
                    <Link
                        :href="show(p.id)"
                        class="flex items-center gap-4 px-5 py-3.5 transition hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="truncate font-medium">{{
                                    p.nombre
                                }}</span>
                                <InsigniaTipo
                                    :tipo="p.tipo"
                                    :etiqueta="p.tipo_etiqueta"
                                />
                            </div>
                            <p
                                class="mt-0.5 truncate text-xs text-muted-foreground"
                            >
                                {{
                                    p.url_destino
                                        ? dominio(p.url_destino)
                                        : 'Sin destino'
                                }}
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="font-semibold tabular-nums">
                                {{ numero(p.total_accesos) }}
                            </p>
                            <p class="text-xs text-muted-foreground">accesos</p>
                        </div>
                        <ArrowRight
                            class="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </Link>
                </li>
            </ul>
        </Panel>

        <Panel
            titulo="Actividad reciente"
            descripcion="Últimos cambios de destino."
        >
            <HistorialDestinos :cambios="actividad.data" mostrar-producto />
        </Panel>
    </div>
</template>
