<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Bot,
    CalendarDays,
    ChartColumnBig,
    Info,
    MousePointerClick,
    Nfc,
    UsersRound,
} from '@lucide/vue';
import { computed, watch } from 'vue';
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
import { index as estadisticasIndex } from '@/routes/admin/estadisticas';
import { show as negocioShow } from '@/routes/admin/negocios';
import { show as productoShow } from '@/routes/admin/productos';
import type {
    Barra,
    ElementoDesglose,
    NegocioTop,
    Opcion,
    ProductoTop,
    PuntoSerie,
    TotalesEstadisticas,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Estadísticas', href: estadisticasIndex() }],
    },
});

const props = defineProps<{
    filtros: {
        desde: string;
        hasta: string;
        negocio_id: string;
        producto_id: string;
    };
    totales: TotalesEstadisticas;
    serie: PuntoSerie[];
    productos_top: ProductoTop[];
    negocios_top: NegocioTop[];
    por_tipo: ElementoDesglose[];
    dispositivos: ElementoDesglose[];
    navegadores: ElementoDesglose[];
    sistemas: ElementoDesglose[];
    negocios: Opcion[];
    productos: Opcion[];
    dias_conservacion_huella: number;
}>();

const { filtros } = useFiltros(estadisticasIndex.url(), { ...props.filtros });

watch(
    () => filtros.negocio_id,
    () => {
        filtros.producto_id = '';
    },
);

const aBarras = (lista: ElementoDesglose[]): Barra[] =>
    lista.map((e) => ({
        clave: e.clave,
        etiqueta: e.etiqueta,
        total: e.total,
    }));

const barrasNegocios = computed<Barra[]>(() =>
    props.negocios_top.map((n) => ({
        clave: n.id,
        etiqueta: n.nombre,
        total: n.total,
        href: negocioShow.url(n.id),
    })),
);

const sinDatos = computed(() => props.totales.total === 0);
</script>

<template>
    <Head title="Estadísticas" />

    <EncabezadoPagina
        titulo="Estadísticas"
        descripcion="Accesos a los enlaces permanentes por periodo, negocio y producto."
    />

    <div
        class="flex flex-col gap-3 rounded-xl border bg-card p-4 shadow-xs xl:flex-row xl:items-center"
    >
        <SelectorPeriodo
            v-model:desde="filtros.desde"
            v-model:hasta="filtros.hasta"
        />
        <div class="flex flex-1 flex-col gap-2 sm:flex-row xl:justify-end">
            <SelectNativo
                v-model="filtros.negocio_id"
                :opciones="negocios"
                vacio="Todos los negocios"
                etiqueta="Filtrar por negocio"
                class="sm:w-56"
            />
            <SelectNativo
                v-model="filtros.producto_id"
                :opciones="productos"
                :vacio="
                    filtros.negocio_id
                        ? 'Todos los productos'
                        : 'Elige un negocio primero'
                "
                etiqueta="Filtrar por producto"
                class="sm:w-56"
            />
        </div>
    </div>

    <p class="text-sm text-muted-foreground">
        Periodo: {{ fechaCorta(filtros.desde) }} –
        {{ fechaCorta(filtros.hasta) }}
    </p>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
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
            etiqueta="Visitantes únicos estimados"
            :valor="visitantes(totales)"
            :icono="UsersRound"
            :detalle="
                coberturaVisitantes(totales) ?? 'Por huella diaria anónima'
            "
        />
        <TarjetaIndicador
            etiqueta="Bots filtrados"
            :valor="numero(totales.bots)"
            :icono="Bot"
            detalle="Excluidos de los totales"
        />
        <TarjetaIndicador
            etiqueta="Productos con accesos"
            :valor="numero(totales.productos_con_accesos)"
            :icono="Nfc"
        />
    </div>

    <Panel v-if="sinDatos">
        <EstadoVacio
            :icono="ChartColumnBig"
            titulo="Sin accesos en este periodo"
            descripcion="Prueba con un rango de fechas más amplio o quita los filtros."
        />
    </Panel>

    <template v-else>
        <Panel titulo="Accesos por día">
            <GraficaArea :serie="serie" etiqueta="Accesos" />
        </Panel>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <Panel
                titulo="Productos más utilizados"
                sin-relleno
                class="lg:col-span-2"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead
                            class="border-b bg-muted/40 text-left text-xs text-muted-foreground"
                        >
                            <tr>
                                <th scope="col" class="px-5 py-2.5 font-medium">
                                    Producto
                                </th>
                                <th scope="col" class="px-3 py-2.5 font-medium">
                                    Negocio
                                </th>
                                <th scope="col" class="px-3 py-2.5 font-medium">
                                    Tipo
                                </th>
                                <th
                                    scope="col"
                                    class="px-5 py-2.5 text-right font-medium"
                                >
                                    Accesos
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="p in productos_top"
                                :key="p.id"
                                class="border-b last:border-0 hover:bg-muted/30"
                            >
                                <td class="px-5 py-2.5">
                                    <Link
                                        :href="productoShow(p.id)"
                                        class="font-medium hover:text-primary hover:underline"
                                        >{{ p.nombre }}</Link
                                    >
                                    <span
                                        class="block font-mono text-xs text-muted-foreground"
                                        >{{ p.codigo }}</span
                                    >
                                </td>
                                <td class="px-3 py-2.5 text-muted-foreground">
                                    {{ p.negocio }}
                                </td>
                                <td
                                    class="px-3 py-2.5 whitespace-nowrap text-muted-foreground"
                                >
                                    {{ p.tipo }}
                                </td>
                                <td
                                    class="px-5 py-2.5 text-right font-semibold tabular-nums"
                                >
                                    {{ numero(p.total) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Panel>
            <Panel titulo="Negocios con mayor actividad">
                <GraficaBarras :barras="barrasNegocios" />
            </Panel>
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <Panel titulo="Accesos por tipo de producto">
                <GraficaBarras :barras="aBarras(por_tipo)" />
                <p class="mt-4 text-xs text-muted-foreground">
                    Cuando QR y NFC comparten el mismo enlace (productos
                    combinados) no es posible saber cuál originó la visita; esos
                    accesos se muestran aparte sin repartirse.
                </p>
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

    <div
        class="flex gap-3 rounded-xl border bg-card p-4 text-sm text-muted-foreground shadow-xs"
    >
        <Info class="mt-0.5 size-4 shrink-0 text-primary" aria-hidden="true" />
        <div class="space-y-1">
            <p>
                Los accesos cuentan solicitudes a los enlaces permanentes, no
                personas. Los bots identificados (vistas previas, rastreadores)
                se excluyen de los totales; la detección es heurística.
            </p>
            <p>
                Los desgloses por dispositivo, navegador y sistema solo cubren
                el periodo de retención de eventos individuales. Los visitantes
                únicos se estiman con una huella diaria anónima que se elimina a
                los {{ dias_conservacion_huella }} días, por lo que fuera de ese
                plazo no se calculan.
            </p>
        </div>
    </div>
</template>
