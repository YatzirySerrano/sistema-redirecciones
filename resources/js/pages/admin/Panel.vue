<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Building2,
    Link2,
    MousePointerClick,
    Nfc,
    Plus,
    Sigma,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import ListaAuditoria from '@/components/comun/ListaAuditoria.vue';
import Panel from '@/components/comun/Panel.vue';
import TarjetaIndicador from '@/components/comun/TarjetaIndicador.vue';
import GraficaArea from '@/components/graficas/GraficaArea.vue';
import GraficaBarras from '@/components/graficas/GraficaBarras.vue';
import HistorialDestinos from '@/components/productos/HistorialDestinos.vue';
import { Button } from '@/components/ui/button';
import { numero } from '@/lib/formato';
import { panel } from '@/routes/admin';
import { index as auditoriaIndex } from '@/routes/admin/auditoria';
import { index as enlacesIndex } from '@/routes/admin/enlaces';
import { index as estadisticasIndex } from '@/routes/admin/estadisticas';
import {
    create as negocioCreate,
    show as negocioShow,
} from '@/routes/admin/negocios';
import {
    create as productoCreate,
    show as productoShow,
} from '@/routes/admin/productos';
import type {
    Barra,
    CambioDestino,
    Coleccion,
    ElementoDesglose,
    NegocioTop,
    ProductoTop,
    PuntoSerie,
    RegistroAuditoria,
    TotalesEstadisticas,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Panel', href: panel() }],
    },
});

const props = defineProps<{
    indicadores: {
        negocios: number;
        negocios_activos: number;
        productos: number;
        productos_qr: number;
        productos_nfc: number;
        redirecciones_activas: number;
        accesos_totales: number;
        periodo: TotalesEstadisticas;
    };
    serie: PuntoSerie[];
    productos_top: ProductoTop[];
    negocios_top: NegocioTop[];
    por_tipo: ElementoDesglose[];
    ultimos_cambios: Coleccion<CambioDestino>;
    actividad: Coleccion<RegistroAuditoria>;
}>();

const barrasProductos = computed<Barra[]>(() =>
    props.productos_top.map((p) => ({
        clave: p.id,
        etiqueta: p.nombre,
        detalle: p.negocio,
        total: p.total,
        href: productoShow.url(p.id),
    })),
);

const barrasNegocios = computed<Barra[]>(() =>
    props.negocios_top.map((n) => ({
        clave: n.id,
        etiqueta: n.nombre,
        total: n.total,
        href: negocioShow.url(n.id),
    })),
);

const barrasTipo = computed<Barra[]>(() =>
    props.por_tipo.map((t) => ({
        clave: t.clave,
        etiqueta: t.etiqueta,
        total: t.total,
    })),
);
</script>

<template>
    <Head title="Panel" />

    <EncabezadoPagina
        titulo="Panel general"
        descripcion="Resumen de negocios, productos y actividad de los enlaces permanentes."
    >
        <template #acciones>
            <Button variant="outline" as-child>
                <Link :href="negocioCreate()"><Building2 /> Nuevo negocio</Link>
            </Button>
            <Button as-child>
                <Link :href="productoCreate()"
                    ><Plus /> Registrar producto</Link
                >
            </Button>
        </template>
    </EncabezadoPagina>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <TarjetaIndicador
            etiqueta="Negocios"
            :valor="numero(indicadores.negocios)"
            :icono="Building2"
            :detalle="`${numero(indicadores.negocios_activos)} activos`"
        />
        <TarjetaIndicador
            etiqueta="Productos registrados"
            :valor="numero(indicadores.productos)"
            :icono="Nfc"
            :detalle="`${numero(indicadores.productos_qr)} con QR · ${numero(indicadores.productos_nfc)} con NFC (los combinados cuentan en ambos)`"
        />
        <TarjetaIndicador
            etiqueta="Redirecciones activas"
            :valor="numero(indicadores.redirecciones_activas)"
            :icono="Link2"
            detalle="Producto y negocio activos, con destino configurado"
        />
        <TarjetaIndicador
            etiqueta="Accesos · últimos 30 días"
            :valor="numero(indicadores.periodo.total)"
            :icono="MousePointerClick"
            :detalle="`Promedio de ${numero(indicadores.periodo.promedio_diario)} al día · bots excluidos`"
        />
        <TarjetaIndicador
            etiqueta="Accesos totales registrados"
            :valor="numero(indicadores.accesos_totales)"
            :icono="Sigma"
            detalle="Histórico acumulado de todos los productos"
        />
        <div
            :title="'Estimación basada en una huella diaria anónima e irreversible (IP + navegador + día). No equivale a personas únicas.'"
        >
            <TarjetaIndicador
                etiqueta="Visitantes únicos estimados · 30 días"
                :valor="numero(indicadores.periodo.visitantes_estimados)"
                :icono="UsersRound"
                detalle="Estimación por huella diaria anónima; no representa personas exactas."
            />
        </div>
    </div>

    <Panel
        titulo="Escaneos por día (30 días)"
        descripcion="Accesos a enlaces permanentes, sin bots identificados."
    >
        <template #acciones>
            <Button variant="ghost" size="sm" as-child>
                <Link :href="estadisticasIndex()">Ver estadísticas</Link>
            </Button>
        </template>
        <GraficaArea :serie="serie" etiqueta="Accesos" />
    </Panel>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <Panel
            titulo="Productos más utilizados"
            descripcion="Últimos 30 días"
            class="lg:col-span-1"
        >
            <GraficaBarras :barras="barrasProductos" />
        </Panel>
        <Panel
            titulo="Negocios con mayor actividad"
            descripcion="Últimos 30 días"
        >
            <GraficaBarras :barras="barrasNegocios" />
        </Panel>
        <Panel titulo="Distribución por tipo" descripcion="Últimos 30 días">
            <GraficaBarras :barras="barrasTipo" />
            <p class="mt-4 text-xs text-muted-foreground">
                Los productos combinados comparten un mismo enlace entre QR y
                NFC, por lo que no es posible saber cuál de los dos originó cada
                acceso.
            </p>
        </Panel>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Panel titulo="Últimos cambios de destino">
            <template #acciones>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="enlacesIndex()">Redirecciones</Link>
                </Button>
            </template>
            <HistorialDestinos
                :cambios="ultimos_cambios.data"
                mostrar-producto
            />
        </Panel>
        <Panel titulo="Actividad reciente">
            <template #acciones>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="auditoriaIndex()">Ver auditoría</Link>
                </Button>
            </template>
            <ListaAuditoria :registros="actividad.data" mostrar-negocio />
        </Panel>
    </div>
</template>
