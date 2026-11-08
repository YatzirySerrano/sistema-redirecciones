<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    BadgeDollarSign,
    Building2,
    CalendarClock,
    CircleAlert,
    CircleCheck,
    CreditCard,
    HeartHandshake,
    LayoutDashboard,
    Link2,
    MailWarning,
    MousePointerClick,
    Nfc,
    Plus,
    ShieldCheck,
    UserRound,
} from '@lucide/vue';
import { computed } from 'vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import ListaAuditoria from '@/components/comun/ListaAuditoria.vue';
import Panel from '@/components/comun/Panel.vue';
import TarjetaIndicador from '@/components/comun/TarjetaIndicador.vue';
import GraficaBarras from '@/components/graficas/GraficaBarras.vue';
import GraficaDona from '@/components/graficas/GraficaDona.vue';
import GraficaLineas from '@/components/graficas/GraficaLineas.vue';
import type { SerieLinea } from '@/components/graficas/GraficaLineas.vue';
import TarjetaGrafica from '@/components/graficas/TarjetaGrafica.vue';
import HistorialDestinos from '@/components/productos/HistorialDestinos.vue';
import { Button } from '@/components/ui/button';
import { usePermisos } from '@/composables/usePermisos';
import { fechaCorta, numero, pesos } from '@/lib/formato';
import { agrupar, CANALES, ORDEN_CANALES, PALETA } from '@/lib/graficas';
import { panel } from '@/routes/admin';
import { index as auditoriaIndex } from '@/routes/admin/auditoria';
import { index as enlacesIndex } from '@/routes/admin/enlaces';
import { index as estadisticasIndex } from '@/routes/admin/estadisticas';
import {
    create as negocioCreate,
    index as negociosIndex,
    show as negocioShow,
} from '@/routes/admin/negocios';
import { index as paginasIndex } from '@/routes/admin/paginas';
import { index as pagosIndex } from '@/routes/admin/pagos';
import {
    create as productoCreate,
    index as productosIndex,
    show as productoShow,
} from '@/routes/admin/productos';
import { index as serviciosIndex } from '@/routes/admin/servicios';
import { index as usuariosIndex } from '@/routes/admin/usuarios';
import type {
    ActividadProducto,
    Barra,
    CambioDestino,
    Coleccion,
    ElementoDesglose,
    NegocioTop,
    PuntoSerieCanales,
    RegistroAuditoria,
    TotalesEstadisticas,
} from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Panel', href: panel() }] },
});

/**
 * Panel de DASTI: estado de la plataforma, uso de los enlaces, incidencias
 * y, para quien tenga permiso, contrataciones e ingresos cobrados. Cada
 * bloque llega del servidor solo si el usuario puede consultarlo.
 */
type Incidencia = {
    clave: string;
    titulo: string;
    descripcion: string;
    cantidad: number;
    ruta: string;
    parametros: Record<string, string>;
    permiso: string;
};

const props = defineProps<{
    indicadores: {
        negocios: number;
        negocios_activos: number;
        productos: number;
        productos_activos: number;
        productos_suspendidos: number;
        enlaces_redirigiendo: number;
        enlaces_sin_destino: number;
        accesos_periodo: TotalesEstadisticas;
        accesos_periodo_anterior: number;
        accesos_totales: number;
    };
    incidencias: Incidencia[];
    negocios: {
        total: number;
        activos: number;
        suspendidos: number;
        nuevos: number;
        nuevos_anterior: number;
    };
    usuarios: {
        total: number;
        activos: number;
        suspendidos: number;
        personal: number;
        clientes: number;
        invitaciones_pendientes: number;
        invitaciones_fallidas: number;
    } | null;
    productos: {
        total: number;
        activos: number;
        suspendidos: number;
        nuevos: number;
        por_tipo: ElementoDesglose[];
    };
    crecimiento:
        | {
              mes: string;
              negocios: number;
              productos: number;
              usuarios: number;
          }[]
        | null;
    contrataciones: {
        por_servicio: {
            clave: string;
            nombre: string;
            vigentes: number;
            restringidas: number;
            vencidas: number;
            nuevas: number;
        }[];
        por_vencer_total: number;
        dias_aviso: number;
    } | null;
    ingresos: {
        neto: number;
        neto_anterior: number;
        pagos: number;
        pendientes_total: number;
        requieren_revision: number;
        pagos_de_prueba: number;
    } | null;
    fidelidad: {
        negocios_con_fidelidad: number;
        contrataciones_pendientes: number;
        pagos_confirmados: number;
        pagos_confirmados_centavos: number;
        pagos_pendientes: number;
        incidencias: number;
    } | null;
    canales: ElementoDesglose[];
    serie: PuntoSerieCanales[];
    serie_anterior: number[];
    productos_top: ActividadProducto[];
    negocios_top: NegocioTop[];
    puede_estadisticas: boolean;
    ultimos_cambios: Coleccion<CambioDestino>;
    actividad: Coleccion<RegistroAuditoria> | null;
}>();

const { puede } = usePermisos();
const i = computed(() => props.indicadores);
const accesos = computed(() => i.value.accesos_periodo.total);

const RUTAS: Record<string, (q: Record<string, string>) => string> = {
    'admin.enlaces.index': (q) => enlacesIndex.url({ query: q }),
    'admin.productos.index': (q) => productosIndex.url({ query: q }),
    'admin.negocios.index': (q) => negociosIndex.url({ query: q }),
    'admin.usuarios.index': (q) => usuariosIndex.url({ query: q }),
    'admin.estadisticas.index': (q) => estadisticasIndex.url({ query: q }),
    'admin.paginas.index': (q) => paginasIndex.url({ query: q }),
    'admin.pagos.index': (q) => pagosIndex.url({ query: q }),
};
const incidencias = computed(() =>
    props.incidencias.filter((x) => puede(x.permiso)),
);

const series = computed<SerieLinea[]>(() => {
    const lista: SerieLinea[] = [
        {
            clave: 'actual',
            nombre: 'Últimos 30 días',
            color: 'var(--primary)',
            valores: props.serie.map((p) => p.total),
            area: true,
        },
    ];

    if (i.value.accesos_periodo_anterior > 0) {
        lista.push({
            clave: 'anterior',
            nombre: '30 días previos',
            color: 'var(--muted-foreground)',
            valores: props.serie_anterior,
            discontinua: true,
        });
    }

    return lista;
});

const segmentosCanal = computed(() =>
    ORDEN_CANALES.map((c) => ({
        clave: c,
        etiqueta: CANALES[c].etiqueta,
        color: CANALES[c].color,
        total: props.canales.find((x) => x.clave === c)?.total ?? 0,
    })),
);
const segmentosTipo = computed(() =>
    props.productos.por_tipo.map((t, k) => ({
        clave: t.clave,
        etiqueta: t.etiqueta,
        total: t.total,
        color: CANALES[t.clave as keyof typeof CANALES]?.color ?? PALETA[k],
    })),
);

const mesFmt = new Intl.DateTimeFormat('es-MX', { month: 'short' });
const mesLargo = new Intl.DateTimeFormat('es-MX', {
    month: 'long',
    year: 'numeric',
});
const puntosCrecimiento = computed(() =>
    (props.crecimiento ?? []).map((m) => {
        const [a, mm] = m.mes.split('-').map(Number);
        const fecha = new Date(a, mm - 1, 1);

        return {
            clave: m.mes,
            eje: mesFmt.format(fecha),
            titulo: mesLargo.format(fecha),
        };
    }),
);
const seriesCrecimiento = computed<SerieLinea[]>(() => [
    {
        clave: 'negocios',
        nombre: 'Negocios',
        color: 'var(--chart-1)',
        valores: (props.crecimiento ?? []).map((m) => m.negocios),
    },
    {
        clave: 'productos',
        nombre: 'Productos',
        color: 'var(--canal-qr)',
        valores: (props.crecimiento ?? []).map((m) => m.productos),
    },
    {
        clave: 'usuarios',
        nombre: 'Usuarios de clientes',
        color: 'var(--chart-4)',
        valores: (props.crecimiento ?? []).map((m) => m.usuarios),
    },
]);
const hayCrecimiento = computed(() =>
    (props.crecimiento ?? []).some(
        (m) => m.negocios + m.productos + m.usuarios > 0,
    ),
);

const barrasProductos = computed<Barra[]>(() =>
    props.productos_top.map((p) => ({
        clave: p.id,
        etiqueta: p.nombre,
        detalle: `${p.negocio} · ${p.tipo}`,
        total: p.total,
        href: puede('productos.ver') ? productoShow.url(p.id) : undefined,
        partes: ORDEN_CANALES.map((c) => ({
            clave: c,
            etiqueta: CANALES[c].corta,
            valor: p[c],
            color: CANALES[c].color,
        })),
    })),
);
const barrasNegocios = computed<Barra[]>(() =>
    props.negocios_top.map((n) => ({
        clave: n.id,
        etiqueta: n.nombre,
        total: n.total,
        href: puede('negocios.ver') ? negocioShow.url(n.id) : undefined,
    })),
);

const vigentes = computed(() =>
    (props.contrataciones?.por_servicio ?? []).reduce(
        (s, x) => s + x.vigentes,
        0,
    ),
);
const semanas = computed(() =>
    agrupar(props.serie, 'semana').map((g) => g.total),
);
</script>

<template>
    <Head title="Panel" />

    <EncabezadoPagina
        titulo="Panel general"
        :descripcion="`Estado de la plataforma y uso de los enlaces del ${fechaCorta(serie[0]?.fecha)} a hoy.`"
        :icono="LayoutDashboard"
    >
        <template #acciones>
            <Button v-if="puede('negocios.crear')" variant="outline" as-child>
                <Link :href="negocioCreate()"><Building2 /> Nuevo negocio</Link>
            </Button>
            <Button v-if="puede('productos.crear')" as-child>
                <Link :href="productoCreate()"
                    ><Plus /> Registrar producto</Link
                >
            </Button>
        </template>
    </EncabezadoPagina>

    <!-- Plataforma -->
    <div
        class="entrada-escalonada grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4"
    >
        <TarjetaIndicador
            etiqueta="Negocios activos"
            :valor="numero(negocios.activos)"
            :icono="Building2"
            :detalle="`de ${numero(negocios.total)} · ${numero(negocios.nuevos)} ${negocios.nuevos === 1 ? 'nuevo' : 'nuevos'} en 30 días${negocios.suspendidos > 0 ? ` · ${numero(negocios.suspendidos)} suspendidos` : ''}`"
            :href="puede('negocios.ver') ? negociosIndex() : undefined"
        />
        <TarjetaIndicador
            etiqueta="Productos activos"
            :valor="numero(productos.activos)"
            :icono="Nfc"
            :detalle="`de ${numero(productos.total)} · ${numero(productos.nuevos)} ${productos.nuevos === 1 ? 'nuevo' : 'nuevos'} en 30 días${productos.suspendidos > 0 ? ` · ${numero(productos.suspendidos)} suspendidos` : ''}`"
            ayuda="Productos físicos vendidos. Una tarjeta QR + NFC con dos destinos cuenta como un solo producto."
            :href="puede('productos.ver') ? productosIndex() : undefined"
        />
        <TarjetaIndicador
            etiqueta="Enlaces que redirigen"
            :valor="numero(i.enlaces_redirigiendo)"
            :detalle="
                i.enlaces_sin_destino === 1
                    ? '1 activo sin destino'
                    : i.enlaces_sin_destino > 1
                      ? `${numero(i.enlaces_sin_destino)} activos sin destino`
                      : 'Todos los enlaces activos tienen destino'
            "
            :icono="Link2"
            :tono="i.enlaces_sin_destino > 0 ? 'alerta' : 'normal'"
            ayuda="Enlaces permanentes de productos y negocios activos que llevan a un destino configurado."
            :href="puede('redirecciones.ver') ? enlacesIndex() : undefined"
        />
        <TarjetaIndicador
            etiqueta="Interacciones · 30 días"
            :valor="numero(accesos)"
            :icono="MousePointerClick"
            :comparacion="{
                actual: accesos,
                anterior: i.accesos_periodo_anterior,
                contexto: 'vs. 30 días previos',
            }"
            :chispa="semanas.length > 1 ? semanas : undefined"
            :ayuda="`Escaneos QR y lecturas NFC que llegaron a su destino, sin bots identificados. Histórico total: ${numero(i.accesos_totales)}.`"
            :href="puede_estadisticas ? estadisticasIndex() : undefined"
        />
    </div>

    <!-- Usuarios -->
    <div v-if="usuarios" class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <Link
            v-for="u in [
                {
                    e: 'Usuarios activos',
                    v: usuarios.activos,
                    d: `de ${numero(usuarios.total)}`,
                    i: UserRound,
                    alerta: false,
                },
                {
                    e: 'Personal DASTI',
                    v: usuarios.personal,
                    d: `${numero(usuarios.clientes)} de clientes`,
                    i: ShieldCheck,
                    alerta: false,
                },
                {
                    e: 'Invitaciones sin aceptar',
                    v: usuarios.invitaciones_pendientes,
                    d: 'Enviadas, sin contraseña definida',
                    i: CalendarClock,
                    alerta: false,
                },
                {
                    e: 'Invitaciones fallidas',
                    v: usuarios.invitaciones_fallidas,
                    d: 'El correo no se pudo enviar',
                    i: MailWarning,
                    alerta: usuarios.invitaciones_fallidas > 0,
                },
            ]"
            :key="u.e"
            :href="usuariosIndex()"
            :class="[
                'flex items-center gap-3 rounded-xl border bg-card px-4 py-3 shadow-xs transition hover:border-primary/40',
                u.alerta && 'border-warning/30 bg-warning-soft/40',
            ]"
        >
            <span
                :class="[
                    'flex size-8 shrink-0 items-center justify-center rounded-lg',
                    u.alerta
                        ? 'bg-warning-soft text-warning'
                        : 'bg-muted text-muted-foreground',
                ]"
                aria-hidden="true"
            >
                <component :is="u.i" class="size-4" />
            </span>
            <span class="min-w-0">
                <span
                    class="block text-lg leading-tight font-semibold tabular-nums"
                    >{{ numero(u.v) }}</span
                >
                <span class="block truncate text-xs font-medium">{{
                    u.e
                }}</span>
                <span
                    class="block truncate text-[11px] text-muted-foreground"
                    >{{ u.d }}</span
                >
            </span>
        </Link>
    </div>

    <!-- Uso y atención -->
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <TarjetaGrafica
            class="xl:col-span-2"
            titulo="Interacciones de la plataforma"
            descripcion="Escaneos y lecturas NFC por día; la línea punteada muestra los 30 días previos."
            :vacio="accesos === 0"
            mensaje-vacio="Ningún enlace registró interacciones en los últimos 30 días."
        >
            <template v-if="puede_estadisticas" #acciones>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="estadisticasIndex()"
                        >Estadísticas <ArrowRight
                    /></Link>
                </Button>
            </template>
            <GraficaLineas
                :puntos="agrupar(serie, 'dia')"
                :series="series"
                :alto="240"
                etiqueta-tabla="Día"
            />
        </TarjetaGrafica>

        <Panel
            titulo="Requiere atención"
            :descripcion="
                incidencias.length === 0
                    ? 'No hay incidencias pendientes.'
                    : 'Situaciones que afectan a clientes, cobros o seguridad.'
            "
            sin-relleno
        >
            <ul v-if="incidencias.length > 0" class="divide-y">
                <li v-for="x in incidencias" :key="x.clave">
                    <Link
                        :href="RUTAS[x.ruta]?.(x.parametros) ?? '#'"
                        class="flex items-start gap-3 px-5 py-3 transition hover:bg-muted/40"
                    >
                        <span
                            class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-warning-soft text-warning"
                            aria-hidden="true"
                        >
                            <CircleAlert class="size-4" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium">{{
                                x.titulo
                            }}</span>
                            <span class="block text-xs text-muted-foreground">{{
                                x.descripcion
                            }}</span>
                        </span>
                        <span
                            class="rounded-full bg-muted px-2 py-0.5 text-xs font-semibold tabular-nums"
                            >{{ numero(x.cantidad) }}</span
                        >
                    </Link>
                </li>
            </ul>
            <div
                v-else
                class="flex items-center gap-3 px-5 py-6 text-sm text-muted-foreground"
            >
                <CircleCheck class="size-5 text-success" aria-hidden="true" />
                Todo en orden.
            </div>
        </Panel>
    </div>

    <!-- Distribuciones y crecimiento -->
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
        <TarjetaGrafica
            titulo="NFC frente a QR"
            descripcion="Canal de las interacciones de los últimos 30 días."
            ayuda="Las tarjetas QR + NFC con un solo enlace compartido no permiten saber qué canal se usó."
            :vacio="accesos === 0"
        >
            <GraficaDona
                :segmentos="segmentosCanal"
                centro="Interacciones"
                :tamano="140"
            />
        </TarjetaGrafica>
        <TarjetaGrafica
            titulo="Productos por tipo"
            descripcion="Inventario registrado de productos físicos."
            :vacio="productos.total === 0"
            mensaje-vacio="Aún no hay productos registrados."
        >
            <GraficaDona
                :segmentos="segmentosTipo"
                centro="Productos"
                :tamano="140"
            />
        </TarjetaGrafica>
        <TarjetaGrafica
            v-if="crecimiento"
            class="md:col-span-2 xl:col-span-1"
            titulo="Altas por mes"
            descripcion="Negocios, productos y usuarios de clientes registrados en los últimos 12 meses."
            :vacio="!hayCrecimiento"
            mensaje-vacio="Sin altas en los últimos 12 meses."
        >
            <GraficaLineas
                :puntos="puntosCrecimiento"
                :series="seriesCrecimiento"
                :alto="200"
                unidad="altas"
                etiqueta-tabla="Mes"
            />
        </TarjetaGrafica>
    </div>

    <!-- Comercial -->
    <section
        v-if="contrataciones || ingresos"
        class="space-y-3"
        aria-labelledby="titulo-comercial"
    >
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 id="titulo-comercial" class="text-sm font-semibold">
                    Servicios e ingresos
                </h2>
                <p class="text-xs text-muted-foreground">
                    Ingresos = pagos cobrados y confirmados, menos reembolsos.
                    No incluye pagos pendientes ni de prueba.
                </p>
            </div>
            <Button
                v-if="puede_estadisticas"
                variant="ghost"
                size="sm"
                as-child
            >
                <Link
                    :href="estadisticasIndex({ query: { vista: 'comercial' } })"
                    >Análisis comercial <ArrowRight
                /></Link>
            </Button>
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <TarjetaIndicador
                v-if="contrataciones"
                etiqueta="Contrataciones vigentes"
                :valor="numero(vigentes)"
                :icono="HeartHandshake"
                :detalle="
                    contrataciones.por_servicio
                        .map((s) => `${s.nombre}: ${numero(s.vigentes)}`)
                        .join(' · ') || 'Sin servicios adicionales'
                "
                :href="serviciosIndex()"
            />
            <TarjetaIndicador
                v-if="contrataciones"
                etiqueta="Por vencer"
                :valor="numero(contrataciones.por_vencer_total)"
                :icono="CalendarClock"
                :tono="
                    contrataciones.por_vencer_total > 0 ? 'alerta' : 'normal'
                "
                :detalle="`Vencen en los próximos ${contrataciones.dias_aviso} días`"
                :href="serviciosIndex()"
            />
            <TarjetaIndicador
                v-if="ingresos"
                etiqueta="Ingresos · 30 días"
                :valor="pesos(ingresos.neto)"
                :icono="BadgeDollarSign"
                :comparacion="{
                    actual: ingresos.neto,
                    anterior: ingresos.neto_anterior,
                    contexto: 'vs. 30 días previos',
                }"
                :detalle="`${numero(ingresos.pagos)} ${ingresos.pagos === 1 ? 'pago confirmado' : 'pagos confirmados'}${ingresos.pagos_de_prueba > 0 ? ` · ${numero(ingresos.pagos_de_prueba)} de prueba excluidos` : ''}`"
                :href="pagosIndex()"
            />
            <TarjetaIndicador
                v-if="ingresos"
                etiqueta="Pagos pendientes"
                :valor="numero(ingresos.pendientes_total)"
                :icono="CreditCard"
                :tono="ingresos.requieren_revision > 0 ? 'alerta' : 'normal'"
                :detalle="
                    ingresos.requieren_revision > 0
                        ? `${numero(ingresos.requieren_revision)} requieren revisión`
                        : 'Órdenes sin confirmar; no son ingresos'
                "
                :href="pagosIndex()"
            />
        </div>
    </section>

    <!-- Servicios adicionales (Fidelidad) -->
    <Panel
        v-if="fidelidad"
        titulo="Fidelidad DASTI"
        descripcion="Servicio adicional contratado por los negocios. Los importes son pagos confirmados, no proyecciones."
    >
        <template #acciones>
            <Button variant="ghost" size="sm" as-child>
                <Link :href="serviciosIndex()"
                    >Contrataciones <ArrowRight
                /></Link>
            </Button>
        </template>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="rounded-lg bg-muted/40 p-3">
                <p
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <HeartHandshake class="size-3.5" aria-hidden="true" />
                    Negocios con Fidelidad
                </p>
                <p class="mt-1 text-xl font-semibold tabular-nums">
                    {{ numero(fidelidad.negocios_con_fidelidad) }}
                </p>
            </div>
            <div class="rounded-lg bg-muted/40 p-3">
                <p class="text-xs text-muted-foreground">
                    Contrataciones pendientes
                </p>
                <p class="mt-1 text-xl font-semibold tabular-nums">
                    {{ numero(fidelidad.contrataciones_pendientes) }}
                </p>
            </div>
            <Link
                :href="pagosIndex()"
                class="rounded-lg bg-muted/40 p-3 transition hover:bg-muted"
            >
                <p
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <CreditCard class="size-3.5" aria-hidden="true" />
                    Pagos confirmados
                </p>
                <p class="mt-1 text-xl font-semibold tabular-nums">
                    {{ numero(fidelidad.pagos_confirmados) }}
                </p>
                <p class="text-xs text-muted-foreground tabular-nums">
                    {{ pesos(fidelidad.pagos_confirmados_centavos) }} ·
                    {{ numero(fidelidad.pagos_pendientes) }}
                    pendientes
                </p>
            </Link>
            <div
                class="rounded-lg p-3"
                :class="
                    fidelidad.incidencias > 0
                        ? 'bg-warning-soft text-warning'
                        : 'bg-muted/40'
                "
            >
                <p class="text-xs">Por revisar</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">
                    {{ numero(fidelidad.incidencias) }}
                </p>
                <p class="text-xs opacity-80">
                    Reembolsos parciales y disputas
                </p>
            </div>
        </div>
    </Panel>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <TarjetaGrafica
            titulo="Productos más usados"
            descripcion="Interacciones en los últimos 30 días, por canal."
            :vacio="barrasProductos.length === 0"
            mensaje-vacio="Ningún producto ha recibido interacciones en 30 días."
        >
            <GraficaBarras :barras="barrasProductos" unidad="interacciones" />
        </TarjetaGrafica>
        <TarjetaGrafica
            titulo="Negocios con más uso"
            descripcion="Suma de las interacciones de sus productos en 30 días."
            :vacio="barrasNegocios.length === 0"
            mensaje-vacio="Ningún negocio ha recibido interacciones en 30 días."
        >
            <GraficaBarras :barras="barrasNegocios" unidad="interacciones" />
        </TarjetaGrafica>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Panel
            titulo="Cambios de destino recientes"
            descripcion="Quién cambió a dónde lleva cada enlace."
        >
            <HistorialDestinos
                :cambios="ultimos_cambios.data"
                mostrar-producto
            />
        </Panel>
        <Panel v-if="actividad" titulo="Actividad reciente">
            <template #acciones>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="auditoriaIndex()"><Activity /> Auditoría</Link>
                </Button>
            </template>
            <ListaAuditoria :registros="actividad.data" mostrar-negocio />
        </Panel>
    </div>
</template>
