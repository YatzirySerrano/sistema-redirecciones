<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChartNoAxesColumn,
    Info,
    MousePointerClick,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import Panel from '@/components/comun/Panel.vue';
import SelectNativo from '@/components/comun/SelectNativo.vue';
import SelectorPeriodo from '@/components/comun/SelectorPeriodo.vue';
import TarjetaIndicador from '@/components/comun/TarjetaIndicador.vue';
import GraficaArea from '@/components/graficas/GraficaArea.vue';
import GraficaBarras from '@/components/graficas/GraficaBarras.vue';
import { useFiltros } from '@/composables/useFiltros';
import {
    coberturaVisitantes,
    fechaCorta,
    numero,
    visitantes,
} from '@/lib/formato';
import { index } from '@/routes/cliente/estadisticas';
import { show } from '@/routes/cliente/productos';
import type {
    Barra,
    ElementoDesglose,
    Opcion,
    ProductoTop,
    PuntoSerie,
    TotalesEstadisticas,
} from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Estadísticas', href: index() }] },
});

const props = defineProps<{
    filtros: { desde: string; hasta: string; producto_id: string };
    totales: TotalesEstadisticas;
    serie: PuntoSerie[];
    productos_top: ProductoTop[];
    dispositivos: ElementoDesglose[];
    navegadores: ElementoDesglose[];
    sistemas: ElementoDesglose[];
    productos: Opcion[];
}>();

const { filtros } = useFiltros(index().url, { ...props.filtros });

const barrasProductos = computed<Barra[]>(() =>
    props.productos_top.map((p) => ({
        clave: p.id,
        etiqueta: p.nombre,
        detalle: p.tipo,
        total: p.total,
        href: show(p.id).url,
    })),
);
const aBarras = (lista: ElementoDesglose[]): Barra[] =>
    lista.map((e) => ({
        clave: e.clave,
        etiqueta: e.etiqueta,
        total: e.total,
    }));
</script>

<template>
    <Head title="Estadísticas" />

    <EncabezadoPagina
        titulo="Estadísticas"
        :descripcion="`Del ${fechaCorta(filtros.desde)} al ${fechaCorta(filtros.hasta)}.`"
    />

    <div
        class="flex flex-col gap-3 rounded-xl border bg-card p-3 shadow-xs lg:flex-row lg:items-center lg:justify-between"
    >
        <SelectorPeriodo
            v-model:desde="filtros.desde"
            v-model:hasta="filtros.hasta"
        />
        <SelectNativo
            v-model="filtros.producto_id"
            :opciones="productos"
            vacio="Todos mis productos"
            etiqueta="Filtrar por producto"
            class="lg:w-64"
        />
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <TarjetaIndicador
            etiqueta="Accesos"
            :valor="numero(totales.total)"
            :icono="MousePointerClick"
        />
        <TarjetaIndicador
            etiqueta="Promedio diario"
            :valor="numero(totales.promedio_diario)"
            :icono="CalendarDays"
        />
        <TarjetaIndicador
            etiqueta="Visitantes estimados"
            :valor="visitantes(totales)"
            :icono="UsersRound"
            :detalle="
                coberturaVisitantes(totales) ??
                'Aproximación por dispositivo y día'
            "
        />
    </div>

    <EstadoVacio
        v-if="totales.total === 0"
        class="rounded-xl border bg-card"
        :icono="ChartNoAxesColumn"
        titulo="Sin accesos en este periodo"
        descripcion="Prueba con un periodo más amplio u otro producto."
    />

    <template v-else>
        <Panel titulo="Accesos por día">
            <GraficaArea :serie="serie" etiqueta="Accesos" />
        </Panel>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <Panel titulo="Accesos por producto">
                <GraficaBarras :barras="barrasProductos" />
            </Panel>
            <Panel titulo="Dispositivos">
                <GraficaBarras :barras="aBarras(dispositivos)" />
            </Panel>
            <Panel titulo="Navegadores">
                <GraficaBarras :barras="aBarras(navegadores)" />
            </Panel>
            <Panel titulo="Sistemas operativos">
                <GraficaBarras :barras="aBarras(sistemas)" />
            </Panel>
        </div>
    </template>

    <p class="flex gap-2 text-xs text-muted-foreground">
        <Info class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
        <span>
            Las cifras son accesos (escaneos o lecturas), no necesariamente
            personas distintas; se excluyen los accesos automatizados
            identificados. Dispositivos y navegadores se deducen de forma
            aproximada y solo cubren el periodo de conservación de los registros
            detallados. Los «visitantes estimados» cuentan dispositivos
            distintos por día y no son exactos.
        </span>
    </p>
</template>
