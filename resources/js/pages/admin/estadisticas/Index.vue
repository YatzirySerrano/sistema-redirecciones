<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BadgeDollarSign,
    Ban,
    Building2,
    CalendarClock,
    ChartNoAxesCombined,
    CircleSlash,
    FilterX,
    Info,
    Receipt,
    Sparkles,
} from '@lucide/vue';
import { computed, watch } from 'vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import SelectorPeriodo from '@/components/comun/SelectorPeriodo.vue';
import TarjetaIndicador from '@/components/comun/TarjetaIndicador.vue';
import AnalisisAccesos from '@/components/estadisticas/AnalisisAccesos.vue';
import FiltroCanal from '@/components/estadisticas/FiltroCanal.vue';
import SelectorBuscable from '@/components/formularios/SelectorBuscable.vue';
import GraficaBarras from '@/components/graficas/GraficaBarras.vue';
import GraficaColumnas from '@/components/graficas/GraficaColumnas.vue';
import GraficaDona from '@/components/graficas/GraficaDona.vue';
import TarjetaGrafica from '@/components/graficas/TarjetaGrafica.vue';
import { Button } from '@/components/ui/button';
import { useFiltros } from '@/composables/useFiltros';
import { usePermisos } from '@/composables/usePermisos';
import { fechaCorta, numero, pesos, relativo } from '@/lib/formato';
import { PALETA } from '@/lib/graficas';
import { cn } from '@/lib/utils';
import { index as estadisticasIndex } from '@/routes/admin/estadisticas';
import { show as negocioShow } from '@/routes/admin/negocios';
import {
    negocios as opcionesNegocios,
    productos as opcionesProductos,
} from '@/routes/admin/opciones';
import { show as productoShow } from '@/routes/admin/productos';
import { index as serviciosIndex } from '@/routes/admin/servicios';
import type {
    AnalisisAccesos as Analisis,
    Barra,
    ElementoDesglose,
    NegocioTop,
    Opcion,
    ProductosSinActividad,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Estadísticas', href: estadisticasIndex() }],
    },
});

type Comercial = {
    contrataciones: {
        por_servicio: {
            clave: string;
            nombre: string;
            vigentes: number;
            restringidas: number;
            suspendidas: number;
            vencidas: number;
            pendientes: number;
            nuevas: number;
            canceladas: number;
        }[];
        nuevas_por_origen: ElementoDesglose[];
        por_vencer_total: number;
        por_vencer: {
            id: number;
            negocio_id: number;
            negocio: string;
            servicio: string;
            vence_en: string;
        }[];
        dias_aviso: number;
    } | null;
    ingresos: {
        neto: number;
        bruto: number;
        reembolsado: number;
        pagos: number;
        neto_anterior: number;
        por_servicio: (ElementoDesglose & { pagos: number })[];
        por_medio: (ElementoDesglose & { pagos: number })[];
        serie_mensual: { mes: string; total: number }[];
        estados: ElementoDesglose[];
        pendientes_total: number;
        requieren_revision: number;
        pagos_de_prueba: number;
        moneda: string;
    } | null;
    negocios: {
        total: number;
        activos: number;
        suspendidos: number;
        nuevos: number;
        nuevos_anterior: number;
    };
    crecimiento: {
        mes: string;
        negocios: number;
        productos: number;
        usuarios: number;
    }[];
};

const props = defineProps<{
    vista: 'uso' | 'comercial';
    puede_comercial: boolean;
    filtros: {
        desde: string;
        hasta: string;
        negocio_id: string;
        producto_id: string;
        canal: string;
    };
    uso?: Analisis & {
        negocios_top: NegocioTop[];
        sin_actividad: ProductosSinActividad | null;
        negocios_sin_actividad: number | null;
    };
    comercial?: Comercial;
    negocio_filtro?: Opcion | null;
    producto_filtro?: Opcion | null;
}>();

const { puede } = usePermisos();
const { filtros } = useFiltros(estadisticasIndex.url(), {
    ...props.filtros,
    vista: props.vista === 'comercial' ? 'comercial' : '',
});

watch(
    () => filtros.negocio_id,
    () => {
        filtros.producto_id = '';
    },
);

const conFiltros = computed(
    () =>
        filtros.negocio_id !== '' ||
        filtros.producto_id !== '' ||
        filtros.canal !== '',
);
const limpiar = () => {
    filtros.negocio_id = '';
    filtros.producto_id = '';
    filtros.canal = '';
};

const barrasNegocios = computed<Barra[]>(() =>
    (props.uso?.negocios_top ?? []).map((n) => ({
        clave: n.id,
        etiqueta: n.nombre,
        total: n.total,
        href: puede('negocios.ver') ? negocioShow.url(n.id) : undefined,
    })),
);

// ---- Comercial ----
const mesFmt = new Intl.DateTimeFormat('es-MX', { month: 'short' });
const mesLargo = new Intl.DateTimeFormat('es-MX', {
    month: 'long',
    year: 'numeric',
});
const fechaMes = (mes: string) => {
    const [a, m] = mes.split('-').map(Number);

    return new Date(a, m - 1, 1);
};
const columnasIngresos = computed(() =>
    (props.comercial?.ingresos?.serie_mensual ?? []).map((m) => ({
        clave: m.mes,
        eje: mesFmt.format(fechaMes(m.mes)),
        titulo: mesLargo.format(fechaMes(m.mes)),
        valor: m.total,
    })),
);
const columnasAltas = computed(() =>
    (props.comercial?.crecimiento ?? []).map((m) => ({
        clave: m.mes,
        eje: mesFmt.format(fechaMes(m.mes)),
        titulo: mesLargo.format(fechaMes(m.mes)),
        valor: m.negocios,
        detalle: `${numero(m.productos)} productos`,
    })),
);
const c = computed(() => props.comercial?.contrataciones ?? null);
const ing = computed(() => props.comercial?.ingresos ?? null);
const sumar = (campo: 'nuevas' | 'canceladas' | 'vigentes') =>
    (c.value?.por_servicio ?? []).reduce((s, x) => s + x[campo], 0);
const segmentos = (lista: ElementoDesglose[]) =>
    lista.map((e, k) => ({
        clave: e.clave,
        etiqueta: e.etiqueta,
        total: e.total,
        color: PALETA[k % PALETA.length],
    }));
const formatoPesos = (v: number) => pesos(v);
</script>

<template>
    <Head title="Estadísticas" />

    <EncabezadoPagina
        titulo="Estadísticas"
        :icono="ChartNoAxesCombined"
        :descripcion="
            vista === 'comercial'
                ? 'Contrataciones, cobros e ingresos confirmados de los servicios.'
                : 'Uso de los enlaces QR y NFC por periodo, negocio, producto y canal.'
        "
    />

    <nav
        v-if="puede_comercial"
        class="flex gap-1 border-b"
        aria-label="Vistas de estadísticas"
    >
        <Link
            v-for="t in [
                { clave: 'uso', etiqueta: 'Uso de enlaces' },
                { clave: 'comercial', etiqueta: 'Comercial' },
            ]"
            :key="t.clave"
            :href="
                estadisticasIndex({
                    query: {
                        ...(t.clave === 'comercial'
                            ? { vista: 'comercial' }
                            : {}),
                        desde: filtros.desde,
                        hasta: filtros.hasta,
                    },
                })
            "
            :aria-current="vista === t.clave ? 'page' : undefined"
            :class="
                cn(
                    '-mb-px border-b-2 px-3 py-2 text-sm font-medium transition',
                    vista === t.clave
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground',
                )
            "
        >
            {{ t.etiqueta }}
        </Link>
    </nav>

    <div
        class="flex flex-col gap-3 rounded-xl border bg-card p-3 shadow-xs 2xl:flex-row 2xl:items-center 2xl:justify-between"
    >
        <SelectorPeriodo
            v-model:desde="filtros.desde"
            v-model:hasta="filtros.hasta"
        />
        <div
            v-if="vista === 'uso'"
            class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center"
        >
            <FiltroCanal v-model="filtros.canal" />
            <SelectorBuscable
                v-model="filtros.negocio_id"
                :url="opcionesNegocios.url()"
                :seleccion-inicial="negocio_filtro ?? null"
                vacio="Todos los negocios"
                etiqueta="Filtrar por negocio"
                placeholder-busqueda="Buscar negocio…"
                class="sm:w-52"
            />
            <SelectorBuscable
                v-model="filtros.producto_id"
                :url="opcionesProductos.url()"
                :parametros="{
                    negocio_id: filtros.negocio_id,
                    principales: '1',
                }"
                :seleccion-inicial="producto_filtro ?? null"
                :vacio="
                    filtros.negocio_id
                        ? 'Todos los productos'
                        : 'Elige un negocio primero'
                "
                :disabled="!filtros.negocio_id"
                etiqueta="Filtrar por producto"
                placeholder-busqueda="Nombre o código…"
                class="sm:w-52"
            />
            <Button v-if="conFiltros" variant="ghost" size="sm" @click="limpiar"
                ><FilterX /> Quitar filtros</Button
            >
        </div>
    </div>

    <!-- ===== Uso de enlaces ===== -->
    <template v-if="vista === 'uso' && uso">
        <AnalisisAccesos
            :analisis="uso"
            :canal="filtros.canal"
            :producto-filtrado="filtros.producto_id !== ''"
            :mostrar-negocio="filtros.negocio_id === ''"
            :enlace-producto="
                puede('productos.ver')
                    ? (id: number) => productoShow.url(id)
                    : undefined
            "
        />

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <TarjetaGrafica
                v-if="filtros.negocio_id === ''"
                titulo="Negocios con más actividad"
                descripcion="Suma de las interacciones de sus productos en el periodo."
                :vacio="barrasNegocios.length === 0"
                mensaje-vacio="Ningún negocio tuvo interacciones en este periodo."
            >
                <GraficaBarras
                    :barras="barrasNegocios"
                    unidad="interacciones"
                />
            </TarjetaGrafica>

            <TarjetaGrafica
                v-if="uso.sin_actividad"
                titulo="Productos activos sin interacciones"
                :descripcion="`${numero(uso.sin_actividad.total)} productos activos de negocios activos no se usaron en el periodo${uso.negocios_sin_actividad !== null ? `; ${numero(uso.negocios_sin_actividad)} negocios activos no tuvieron ninguna interacción` : ''}.`"
                :vacio="uso.sin_actividad.total === 0"
                :icono-vacio="Sparkles"
                mensaje-vacio="Todos los productos activos tuvieron actividad en el periodo."
            >
                <ul class="divide-y rounded-lg border">
                    <li
                        v-for="p in uso.sin_actividad.productos"
                        :key="p.id"
                        class="flex items-center gap-3 px-3 py-2 text-sm"
                    >
                        <span class="min-w-0 flex-1">
                            <component
                                :is="puede('productos.ver') ? Link : 'span'"
                                :href="
                                    puede('productos.ver')
                                        ? productoShow(p.id)
                                        : undefined
                                "
                                class="block truncate font-medium hover:text-primary"
                                >{{ p.nombre }}</component
                            >
                            <span class="text-xs text-muted-foreground"
                                >{{ p.tipo }} · {{ p.codigo }}</span
                            >
                        </span>
                        <span class="shrink-0 text-xs text-muted-foreground">
                            {{
                                p.ultimo_acceso_en
                                    ? relativo(p.ultimo_acceso_en)
                                    : 'Nunca usado'
                            }}
                        </span>
                    </li>
                </ul>
            </TarjetaGrafica>
        </div>

        <p class="flex gap-2 text-xs text-pretty text-muted-foreground">
            <Info class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
            <span>
                Interacciones = accesos registrados que llegaron a su destino,
                sin bots identificados; no son personas, ventas ni conversiones.
                El análisis global se actualiza cada pocos minutos. No se
                registra ubicación.
            </span>
        </p>
    </template>

    <!-- ===== Comercial ===== -->
    <template v-else-if="vista === 'comercial' && comercial">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <TarjetaIndicador
                v-if="ing"
                etiqueta="Ingresos confirmados"
                :valor="pesos(ing.neto)"
                :icono="BadgeDollarSign"
                :comparacion="{ actual: ing.neto, anterior: ing.neto_anterior }"
                :detalle="
                    ing.reembolsado > 0
                        ? `${pesos(ing.bruto)} cobrados − ${pesos(ing.reembolsado)} reembolsados`
                        : `${numero(ing.pagos)} ${ing.pagos === 1 ? 'pago' : 'pagos'} en el periodo`
                "
                ayuda="Pagos cobrados y confirmados (en línea en modo productivo o registrados fuera de línea por DASTI), menos reembolsos. No incluye órdenes pendientes ni pagos de prueba."
            />
            <TarjetaIndicador
                v-if="c"
                etiqueta="Contrataciones nuevas"
                :valor="numero(sumar('nuevas'))"
                :icono="Receipt"
                :detalle="`${numero(sumar('vigentes'))} vigentes hoy`"
                ayuda="Contrataciones registradas en el periodo, de cualquier origen. Una renovación se registra como una contratación nueva."
            />
            <TarjetaIndicador
                v-if="c"
                etiqueta="Cancelaciones"
                :valor="numero(sumar('canceladas'))"
                :icono="Ban"
                detalle="Contrataciones canceladas en el periodo"
            />
            <TarjetaIndicador
                v-if="c"
                etiqueta="Por vencer"
                :valor="numero(c.por_vencer_total)"
                :icono="CalendarClock"
                :tono="c.por_vencer_total > 0 ? 'alerta' : 'normal'"
                :detalle="`En los próximos ${c.dias_aviso} días`"
                :href="serviciosIndex()"
            />
            <TarjetaIndicador
                v-if="!c"
                etiqueta="Negocios nuevos"
                :valor="numero(comercial.negocios.nuevos)"
                :icono="Building2"
                :comparacion="{
                    actual: comercial.negocios.nuevos,
                    anterior: comercial.negocios.nuevos_anterior,
                }"
            />
        </div>

        <div v-if="ing" class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <TarjetaGrafica
                class="xl:col-span-2"
                titulo="Ingresos por mes"
                descripcion="Ingresos confirmados netos de los últimos 12 meses (no depende del periodo elegido)."
                :vacio="columnasIngresos.every((x) => x.valor === 0)"
                :icono-vacio="BadgeDollarSign"
                mensaje-vacio="Aún no hay ingresos confirmados. Los pagos de prueba de Openpay no se cuentan."
            >
                <GraficaColumnas
                    :columnas="columnasIngresos"
                    :formato="formatoPesos"
                    unidad=""
                    :alto="200"
                />
            </TarjetaGrafica>
            <TarjetaGrafica
                titulo="Ingresos por servicio"
                descripcion="Reparto de los ingresos confirmados del periodo."
                :vacio="ing.por_servicio.length === 0"
                mensaje-vacio="Sin ingresos confirmados en el periodo."
            >
                <GraficaDona
                    :segmentos="segmentos(ing.por_servicio)"
                    centro="Pagos"
                    :tamano="140"
                />
                <ul class="mt-3 space-y-1 text-xs text-muted-foreground">
                    <li
                        v-for="s in ing.por_servicio"
                        :key="s.clave"
                        class="flex justify-between gap-2"
                    >
                        <span>{{ s.etiqueta }}</span>
                        <span
                            class="font-medium text-foreground tabular-nums"
                            >{{ pesos(s.total) }}</span
                        >
                    </li>
                </ul>
            </TarjetaGrafica>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <TarjetaGrafica
                v-if="ing"
                titulo="Ingresos por medio de pago"
                descripcion="En línea (Openpay) frente a pagos fuera de línea registrados por DASTI."
                :vacio="ing.por_medio.length === 0"
                mensaje-vacio="Sin ingresos confirmados en el periodo."
            >
                <ul class="space-y-3">
                    <li
                        v-for="m in ing.por_medio"
                        :key="m.clave"
                        class="flex items-center justify-between gap-3 text-sm"
                    >
                        <span class="min-w-0">
                            <span class="block truncate font-medium">{{
                                m.etiqueta
                            }}</span>
                            <span class="text-xs text-muted-foreground"
                                >{{ numero(m.pagos) }} pagos</span
                            >
                        </span>
                        <span class="font-semibold tabular-nums">{{
                            pesos(m.total)
                        }}</span>
                    </li>
                </ul>
            </TarjetaGrafica>
            <TarjetaGrafica
                v-if="ing"
                titulo="Órdenes de pago del periodo"
                descripcion="Estado de las órdenes creadas en el periodo. Solo «Pagado» y los reembolsos parciales son dinero cobrado."
                :nota="
                    ing.pagos_de_prueba > 0
                        ? `${numero(ing.pagos_de_prueba)} órdenes son de prueba (sandbox) y no cuentan como ingreso.`
                        : null
                "
                :vacio="ing.estados.every((e) => e.total === 0)"
                :icono-vacio="CircleSlash"
                mensaje-vacio="No se crearon órdenes de pago en el periodo."
            >
                <GraficaBarras
                    :barras="
                        ing.estados
                            .filter((e) => e.total > 0)
                            .map((e) => ({
                                clave: e.clave,
                                etiqueta: e.etiqueta,
                                total: e.total,
                            }))
                    "
                    unidad="órdenes"
                />
            </TarjetaGrafica>
        </div>

        <div v-if="c" class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <TarjetaGrafica
                class="xl:col-span-2"
                titulo="Contrataciones por servicio"
                descripcion="Situación actual de cada servicio adicional; «nuevas» y «canceladas» corresponden al periodo."
                :vacio="c.por_servicio.length === 0"
                mensaje-vacio="Aún no hay contrataciones de servicios adicionales."
            >
                <div class="-mx-1 overflow-x-auto px-1">
                    <table class="w-full min-w-[560px] text-sm">
                        <thead class="text-left text-xs text-muted-foreground">
                            <tr class="border-b">
                                <th scope="col" class="py-2 pr-3 font-medium">
                                    Servicio
                                </th>
                                <th
                                    v-for="h in [
                                        'Vigentes',
                                        'Restringidas',
                                        'Suspendidas',
                                        'Vencidas',
                                        'Pendientes',
                                        'Nuevas',
                                        'Canceladas',
                                    ]"
                                    :key="h"
                                    scope="col"
                                    class="px-2 py-2 text-right font-medium"
                                >
                                    {{ h }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="s in c.por_servicio"
                                :key="s.clave"
                                class="border-b last:border-0"
                            >
                                <th
                                    scope="row"
                                    class="py-2 pr-3 text-left font-medium"
                                >
                                    {{ s.nombre }}
                                </th>
                                <td
                                    class="px-2 py-2 text-right font-semibold tabular-nums"
                                >
                                    {{ numero(s.vigentes) }}
                                </td>
                                <td class="px-2 py-2 text-right tabular-nums">
                                    {{ numero(s.restringidas) }}
                                </td>
                                <td class="px-2 py-2 text-right tabular-nums">
                                    {{ numero(s.suspendidas) }}
                                </td>
                                <td class="px-2 py-2 text-right tabular-nums">
                                    {{ numero(s.vencidas) }}
                                </td>
                                <td class="px-2 py-2 text-right tabular-nums">
                                    {{ numero(s.pendientes) }}
                                </td>
                                <td class="px-2 py-2 text-right tabular-nums">
                                    {{ numero(s.nuevas) }}
                                </td>
                                <td class="px-2 py-2 text-right tabular-nums">
                                    {{ numero(s.canceladas) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </TarjetaGrafica>
            <TarjetaGrafica
                titulo="Origen de las contrataciones nuevas"
                descripcion="Cómo se registraron las contrataciones del periodo."
                :vacio="c.nuevas_por_origen.every((o) => o.total === 0)"
                mensaje-vacio="Sin contrataciones nuevas en el periodo."
            >
                <GraficaBarras
                    :barras="
                        c.nuevas_por_origen.map((o) => ({
                            clave: o.clave,
                            etiqueta: o.etiqueta,
                            total: o.total,
                        }))
                    "
                    unidad="contrataciones"
                />
            </TarjetaGrafica>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <TarjetaGrafica
                v-if="c"
                titulo="Próximas a vencer"
                :descripcion="`Contrataciones activas que vencen en los próximos ${c.dias_aviso} días.`"
                :vacio="c.por_vencer.length === 0"
                :icono-vacio="CalendarClock"
                mensaje-vacio="Ninguna contratación vence pronto."
            >
                <ul class="divide-y rounded-lg border">
                    <li
                        v-for="v in c.por_vencer"
                        :key="v.id"
                        class="flex items-center gap-3 px-3 py-2 text-sm"
                    >
                        <span class="min-w-0 flex-1">
                            <component
                                :is="puede('negocios.ver') ? Link : 'span'"
                                :href="
                                    puede('negocios.ver')
                                        ? negocioShow(v.negocio_id)
                                        : undefined
                                "
                                class="block truncate font-medium hover:text-primary"
                                >{{ v.negocio }}</component
                            >
                            <span class="text-xs text-muted-foreground">{{
                                v.servicio
                            }}</span>
                        </span>
                        <span class="shrink-0 text-right text-xs">
                            <span class="block font-medium">{{
                                fechaCorta(v.vence_en)
                            }}</span>
                            <span class="text-muted-foreground">{{
                                relativo(v.vence_en)
                            }}</span>
                        </span>
                    </li>
                </ul>
            </TarjetaGrafica>
            <TarjetaGrafica
                titulo="Negocios registrados por mes"
                descripcion="Altas de negocios en los últimos 12 meses."
                :vacio="columnasAltas.every((x) => x.valor === 0)"
                :icono-vacio="Building2"
                mensaje-vacio="Sin negocios nuevos en los últimos 12 meses."
            >
                <GraficaColumnas
                    :columnas="columnasAltas"
                    unidad="negocios"
                    :alto="180"
                />
            </TarjetaGrafica>
        </div>

        <p class="flex gap-2 text-xs text-pretty text-muted-foreground">
            <Info class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
            <span>
                Los importes son en {{ ing?.moneda ?? 'MXN' }}. Un pago
                pendiente, una orden generada o el precio de un servicio nunca
                se cuentan como ingreso. Mientras Openpay opere en modo de
                pruebas, sus pagos se muestran aparte y no suman.
            </span>
        </p>
    </template>
</template>
