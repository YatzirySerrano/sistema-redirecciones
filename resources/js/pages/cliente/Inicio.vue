<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    ChartNoAxesCombined,
    CircleAlert,
    Crown,
    Gift,
    HeartHandshake,
    LayoutList,
    Link2,
    MousePointerClick,
    Nfc,
    QrCode,
    Repeat,
    Sparkles,
    Stamp,
    UsersRound,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import Panel from '@/components/comun/Panel.vue';
import TarjetaIndicador from '@/components/comun/TarjetaIndicador.vue';
import GraficaBarras from '@/components/graficas/GraficaBarras.vue';
import GraficaDona from '@/components/graficas/GraficaDona.vue';
import GraficaLineas from '@/components/graficas/GraficaLineas.vue';
import type { SerieLinea } from '@/components/graficas/GraficaLineas.vue';
import IndicadorTendencia from '@/components/graficas/IndicadorTendencia.vue';
import TarjetaGrafica from '@/components/graficas/TarjetaGrafica.vue';
import HistorialDestinos from '@/components/productos/HistorialDestinos.vue';
import ResumenServicios from '@/components/servicios/ResumenServicios.vue';
import { Button } from '@/components/ui/button';
import { usePermisos } from '@/composables/usePermisos';
import { numero, relativo } from '@/lib/formato';
import { agrupar, CANALES, ORDEN_CANALES, porcentajeDe } from '@/lib/graficas';
import { inicio } from '@/routes/cliente';
import { index as estadisticas } from '@/routes/cliente/estadisticas';
import {
    inicio as fidelidadInicio,
    mostrador,
} from '@/routes/cliente/fidelidad';
import { inicio as paginaInicio } from '@/routes/cliente/paginas';
import {
    index as productos,
    show as producto,
} from '@/routes/cliente/productos';
import { index as servicios } from '@/routes/cliente/servicios';
import type {
    ActividadProducto,
    Barra,
    CambioDestino,
    Coleccion,
    ElementoDesglose,
    ProductosSinActividad,
    PuntoSerieCanales,
    TotalesEstadisticas,
} from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Inicio', href: inicio() }] },
});

/**
 * Inicio del cliente: resumen ejecutivo de los últimos 30 días. Responde
 * «¿se están usando mis productos?», «¿cuáles más?», «¿hay algo que
 * atender?» y lleva a las acciones frecuentes. El análisis detallado vive
 * en Estadísticas.
 */
const props = defineProps<{
    negocio: { nombre_comercial: string };
    indicadores: {
        productos: number;
        productos_activos: number;
        enlaces_sin_destino: number;
        enlaces: number;
        enlaces_con_destino: number;
        accesos_30_dias: TotalesEstadisticas | null;
        accesos_previos: number | null;
    };
    ver_estadisticas: boolean;
    serie: PuntoSerieCanales[] | null;
    serie_anterior: number[] | null;
    canales: ElementoDesglose[];
    productos_top: ActividadProducto[];
    sin_actividad: ProductosSinActividad | null;
    alertas: {
        clave: string;
        titulo: string;
        descripcion: string;
        producto_id: number | null;
    }[];
    actividad: Coleccion<CambioDestino>;
    fidelidad: {
        consumidores: number;
        visitas_periodo: number;
        dias_periodo: number;
        recurrentes: number;
        recompensas_disponibles: number;
        recompensas_canjeadas_periodo: number;
        proximos_a_meta: {
            consumidor_id: number;
            nombre: string;
            campana: string;
            visitas: number;
            meta: number;
            faltan: number;
        }[];
    } | null;
}>();

const page = usePage();
const { puede } = usePermisos();
const nombre = computed(() => page.props.auth.user.name.split(' ')[0]);
const saludo = computed(() => {
    const h = new Date().getHours();

    return h < 12 ? 'Buenos días' : h < 19 ? 'Buenas tardes' : 'Buenas noches';
});

const accesos = computed(() => props.indicadores.accesos_30_dias);
const total = computed(() => accesos.value?.total ?? 0);
const previo = computed(() => props.indicadores.accesos_previos ?? 0);
const destacado = computed(() => props.productos_top[0] ?? null);

const resumen = computed(() => {
    if (!accesos.value) {
        return `Así están los productos de ${props.negocio.nombre_comercial}.`;
    }

    if (total.value === 0) {
        return props.indicadores.productos === 0
            ? 'Aún no tienes productos registrados.'
            : 'Tus productos no registraron interacciones en los últimos 30 días.';
    }

    return `En los últimos 30 días tus productos se usaron ${numero(total.value)} ${total.value === 1 ? 'vez' : 'veces'}.`;
});

const series = computed<SerieLinea[]>(() => {
    if (!props.serie) {
        return [];
    }

    const lista: SerieLinea[] = [
        {
            clave: 'actual',
            nombre: 'Últimos 30 días',
            color: 'var(--primary)',
            valores: props.serie.map((p) => p.total),
            area: true,
        },
    ];

    if (props.serie_anterior && previo.value > 0) {
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
    })).filter((s) => s.total > 0),
);

const barras = computed<Barra[]>(() =>
    props.productos_top.map((p) => ({
        clave: p.id,
        etiqueta: p.nombre,
        detalle: p.tipo,
        total: p.total,
        href: producto.url(p.id),
        partes: ORDEN_CANALES.map((c) => ({
            clave: c,
            etiqueta: CANALES[c].corta,
            valor: p[c],
            color: CANALES[c].color,
        })),
    })),
);

const servicioActivo = (clave: 'fidelidad' | 'pagina_enlaces') => {
    const s = page.props.servicios?.[clave];

    return !!s && (s.activo || s.solo_lectura);
};

type Acceso = {
    titulo: string;
    descripcion: string;
    href: string;
    icono: Component;
};

const accesosRapidos = computed<Acceso[]>(() => {
    const lista: (Acceso | false)[] = [
        puede('productos.ver') && {
            titulo: 'Mis productos',
            descripcion: 'Tarjetas, códigos y su estado',
            href: productos.url(),
            icono: Nfc,
        },
        puede('redirecciones.modificar_destino') &&
            puede('productos.ver') && {
                titulo: 'Cambiar un destino',
                descripcion: 'Elige a dónde lleva cada enlace',
                href: productos.url(),
                icono: Link2,
            },
        puede('qr.descargar') &&
            puede('productos.ver') && {
                titulo: 'Descargar códigos QR',
                descripcion: 'Desde la ficha de cada producto',
                href: productos.url(),
                icono: QrCode,
            },
        props.ver_estadisticas && {
            titulo: 'Estadísticas',
            descripcion: 'Horarios, canales y dispositivos',
            href: estadisticas.url(),
            icono: ChartNoAxesCombined,
        },
        servicioActivo('fidelidad') &&
            puede('fidelidad.visitas') && {
                titulo: 'Registrar visita',
                descripcion: 'Mostrador de Fidelidad',
                href: mostrador.url(),
                icono: Stamp,
            },
        servicioActivo('pagina_enlaces') &&
            puede('paginas.ver') && {
                titulo: 'Mi página de enlaces',
                descripcion: 'Dirección pública y publicación',
                href: paginaInicio.url(),
                icono: LayoutList,
            },
        puede('servicios.ver') && {
            titulo: 'Mis servicios',
            descripcion: 'Servicios contratados y disponibles',
            href: servicios.url(),
            icono: Sparkles,
        },
    ];

    return lista.filter((x): x is Acceso => x !== false);
});

const semanas = computed(() =>
    props.serie ? agrupar(props.serie, 'semana').map((g) => g.total) : [],
);
</script>

<template>
    <Head title="Inicio" />

    <!-- Encabezado con el resumen del periodo -->
    <section
        class="relative overflow-hidden rounded-2xl border bg-card p-5 shadow-xs sm:p-6"
        aria-labelledby="titulo-inicio"
    >
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(120%_120%_at_100%_0%,color-mix(in_oklab,var(--primary)_14%,transparent),transparent_60%)]"
            aria-hidden="true"
        />
        <div
            class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"
        >
            <div class="min-w-0">
                <p
                    class="text-xs font-medium tracking-wide text-primary uppercase"
                >
                    {{ negocio.nombre_comercial }}
                </p>
                <h1
                    id="titulo-inicio"
                    class="mt-1 text-2xl font-semibold tracking-tight text-balance sm:text-3xl"
                >
                    {{ saludo }}, {{ nombre }}
                </h1>
                <p
                    class="mt-1.5 max-w-xl text-sm text-pretty text-muted-foreground"
                >
                    {{ resumen }}
                </p>
                <IndicadorTendencia
                    v-if="accesos && (total > 0 || previo > 0)"
                    class="mt-2"
                    :actual="total"
                    :anterior="previo"
                    contexto="frente a los 30 días previos"
                />
            </div>
            <div class="flex flex-wrap gap-2">
                <Button v-if="puede('productos.ver')" as-child>
                    <Link :href="productos()"><Nfc /> Mis productos</Link>
                </Button>
                <Button v-if="ver_estadisticas" variant="outline" as-child>
                    <Link :href="estadisticas()"
                        ><ChartNoAxesCombined /> Ver estadísticas</Link
                    >
                </Button>
            </div>
        </div>
    </section>

    <!-- Alertas que requieren atención -->
    <div v-if="alertas.length > 0" class="grid gap-2 md:grid-cols-2">
        <component
            :is="a.producto_id ? Link : 'div'"
            v-for="a in alertas"
            :key="a.clave"
            :href="a.producto_id ? producto(a.producto_id) : undefined"
            class="flex items-start gap-3 rounded-xl border border-warning/30 bg-warning-soft p-4 text-warning transition hover:border-warning/50"
            role="status"
        >
            <CircleAlert class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-semibold">{{ a.titulo }}</span>
                <span class="block text-sm opacity-90">{{
                    a.descripcion
                }}</span>
            </span>
            <ArrowRight
                v-if="a.producto_id"
                class="mt-0.5 size-4 shrink-0"
                aria-hidden="true"
            />
        </component>
    </div>

    <!-- Indicadores -->
    <div
        class="entrada-escalonada grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4"
    >
        <TarjetaIndicador
            v-if="accesos"
            etiqueta="Interacciones · 30 días"
            :valor="numero(total)"
            :icono="MousePointerClick"
            :comparacion="{
                actual: total,
                anterior: previo,
                contexto: 'vs. 30 días previos',
            }"
            :chispa="semanas.length > 1 ? semanas : undefined"
            ayuda="Escaneos de QR y lecturas NFC que llegaron a su destino, sin contar accesos automatizados identificados. No equivale a personas distintas."
        />

        <section
            v-if="accesos"
            class="flex flex-col rounded-xl border bg-card p-4 shadow-xs sm:p-5"
            aria-labelledby="titulo-canales"
        >
            <h2
                id="titulo-canales"
                class="text-xs font-medium text-muted-foreground sm:text-sm"
            >
                Cómo se abren tus enlaces
            </h2>
            <div v-if="segmentosCanal.length > 0" class="mt-3">
                <GraficaDona
                    :segmentos="segmentosCanal"
                    centro="Total"
                    :tamano="84"
                    compacta
                />
            </div>
            <p v-else class="mt-3 text-sm text-muted-foreground">
                Verás aquí la proporción entre NFC y QR cuando haya
                interacciones.
            </p>
        </section>

        <TarjetaIndicador
            v-if="accesos"
            etiqueta="Producto más usado"
            :valor="destacado ? destacado.nombre : '—'"
            :icono="Crown"
            :detalle="
                destacado
                    ? `${numero(destacado.total)} interacciones · ${porcentajeDe(destacado.total, total)} del total`
                    : 'Sin interacciones en 30 días'
            "
            :href="destacado ? producto(destacado.id) : undefined"
        />

        <TarjetaIndicador
            etiqueta="Productos activos"
            :valor="`${numero(indicadores.productos_activos)} de ${numero(indicadores.productos)}`"
            :icono="Nfc"
            :tono="indicadores.enlaces_sin_destino > 0 ? 'alerta' : 'normal'"
            :detalle="
                indicadores.enlaces_sin_destino > 0
                    ? `${numero(indicadores.enlaces_sin_destino)} ${indicadores.enlaces_sin_destino === 1 ? 'enlace sin destino' : 'enlaces sin destino'}`
                    : indicadores.enlaces > 0
                      ? 'Todos los enlaces activos tienen destino'
                      : 'Aún no tienes productos'
            "
            :href="puede('productos.ver') ? productos() : undefined"
        />
    </div>

    <!-- Tendencia y productos -->
    <div v-if="accesos && serie" class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <TarjetaGrafica
            class="xl:col-span-2"
            titulo="Actividad de los últimos 30 días"
            descripcion="Interacciones por día; la línea punteada muestra los 30 días previos."
            :vacio="total === 0"
            mensaje-vacio="Cuando alguien use tus tarjetas o códigos, aquí verás la actividad día con día."
        >
            <template #acciones>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="estadisticas()"
                        >Análisis completo <ArrowRight
                    /></Link>
                </Button>
            </template>
            <GraficaLineas
                :puntos="agrupar(serie, 'dia')"
                :series="series"
                :alto="230"
                etiqueta-tabla="Día"
            />
        </TarjetaGrafica>
        <TarjetaGrafica
            titulo="Productos más usados"
            descripcion="Interacciones en 30 días, por canal."
            :vacio="barras.length === 0"
            mensaje-vacio="Ningún producto se ha usado en los últimos 30 días."
        >
            <GraficaBarras :barras="barras" unidad="interacciones" />
        </TarjetaGrafica>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Accesos rápidos -->
        <section
            class="rounded-xl border bg-card p-4 shadow-xs sm:p-5"
            aria-labelledby="titulo-accesos"
        >
            <h2 id="titulo-accesos" class="text-sm font-semibold">
                Accesos rápidos
            </h2>
            <ul
                class="entrada-escalonada mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2"
            >
                <li v-for="r in accesosRapidos" :key="r.titulo">
                    <Link
                        :href="r.href"
                        class="group tarjeta-interactiva flex items-center gap-3 rounded-lg border p-3 hover:bg-primary/5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary transition-transform duration-200 ease-out group-hover:scale-110 group-hover:-rotate-3"
                            aria-hidden="true"
                        >
                            <component :is="r.icono" class="size-4" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">{{
                                r.titulo
                            }}</span>
                            <span
                                class="block truncate text-xs text-muted-foreground"
                                >{{ r.descripcion }}</span
                            >
                        </span>
                        <ArrowRight
                            class="size-4 shrink-0 text-muted-foreground transition group-hover:translate-x-0.5 group-hover:text-primary"
                            aria-hidden="true"
                        />
                    </Link>
                </li>
            </ul>
        </section>

        <!-- Productos sin uso -->
        <section
            v-if="sin_actividad && sin_actividad.total > 0"
            class="rounded-xl border bg-card p-4 shadow-xs sm:p-5"
            aria-labelledby="titulo-sin-uso"
        >
            <h2 id="titulo-sin-uso" class="text-sm font-semibold">
                Productos sin uso en 30 días
            </h2>
            <p class="mt-0.5 text-xs text-muted-foreground">
                {{ numero(sin_actividad.total) }}
                {{
                    sin_actividad.total === 1
                        ? 'producto activo no registró'
                        : 'productos activos no registraron'
                }}
                interacciones. Revisa si están a la vista de tus clientes.
            </p>
            <ul class="mt-3 divide-y rounded-lg border">
                <li
                    v-for="p in sin_actividad.productos"
                    :key="p.id"
                    class="flex items-center gap-3 px-3 py-2 text-sm"
                >
                    <Link
                        :href="producto(p.id)"
                        class="min-w-0 flex-1 truncate font-medium hover:text-primary hover:underline"
                    >
                        {{ p.nombre }}
                    </Link>
                    <span class="shrink-0 text-xs text-muted-foreground">
                        {{
                            p.ultimo_acceso_en
                                ? relativo(p.ultimo_acceso_en)
                                : 'Nunca usado'
                        }}
                    </span>
                </li>
            </ul>
        </section>
        <ResumenServicios v-else-if="puede('servicios.ver')" />
    </div>

    <ResumenServicios
        v-if="
            puede('servicios.ver') && sin_actividad && sin_actividad.total > 0
        "
    />

    <!-- Fidelidad (si el negocio la contrató) -->
    <Panel
        v-if="fidelidad"
        titulo="Programa de fidelidad"
        :descripcion="`Últimos ${fidelidad.dias_periodo} días. Las visitas solo cuentan cuando tu personal las confirma.`"
    >
        <template #acciones>
            <Button v-if="puede('fidelidad.visitas')" size="sm" as-child>
                <Link :href="mostrador()"><Stamp /> Registrar visita</Link>
            </Button>
            <Button variant="ghost" size="sm" as-child>
                <Link :href="fidelidadInicio()"
                    >Ver programa <ArrowRight
                /></Link>
            </Button>
        </template>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
            <div
                v-for="d in [
                    {
                        e: 'Consumidores inscritos',
                        v: fidelidad.consumidores,
                        i: UsersRound,
                    },
                    {
                        e: 'Visitas válidas',
                        v: fidelidad.visitas_periodo,
                        i: Stamp,
                    },
                    {
                        e: 'Clientes recurrentes',
                        v: fidelidad.recurrentes,
                        i: Repeat,
                    },
                    {
                        e: 'Recompensas disponibles',
                        v: fidelidad.recompensas_disponibles,
                        i: Gift,
                    },
                    {
                        e: 'Recompensas canjeadas',
                        v: fidelidad.recompensas_canjeadas_periodo,
                        i: HeartHandshake,
                    },
                ]"
                :key="d.e"
                class="rounded-lg bg-muted/40 p-3"
            >
                <p
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <component :is="d.i" class="size-3.5" aria-hidden="true" />
                    {{ d.e }}
                </p>
                <p class="mt-1 text-xl font-semibold tabular-nums">
                    {{ numero(d.v) }}
                </p>
            </div>
        </div>
        <div v-if="fidelidad.proximos_a_meta.length > 0" class="mt-5">
            <p class="mb-2 text-xs font-medium text-muted-foreground">
                Próximos a completar su meta
            </p>
            <ul class="divide-y rounded-lg border">
                <li
                    v-for="c in fidelidad.proximos_a_meta"
                    :key="`${c.consumidor_id}-${c.campana}`"
                    class="flex items-center gap-3 px-3 py-2 text-sm"
                >
                    <span class="min-w-0 flex-1 truncate font-medium">{{
                        c.nombre
                    }}</span>
                    <span
                        class="hidden truncate text-xs text-muted-foreground sm:inline"
                        >{{ c.campana }}</span
                    >
                    <span class="w-28 shrink-0">
                        <span
                            class="block h-1.5 overflow-hidden rounded-full bg-muted"
                        >
                            <span
                                class="block h-full rounded-full bg-primary"
                                :style="{
                                    width: `${Math.min(100, (c.visitas / c.meta) * 100)}%`,
                                }"
                            />
                        </span>
                    </span>
                    <span
                        class="w-20 shrink-0 text-right text-xs text-muted-foreground tabular-nums"
                        >faltan {{ c.faltan }}</span
                    >
                </li>
            </ul>
        </div>
    </Panel>

    <Panel
        titulo="Cambios de destino recientes"
        descripcion="Los últimos cambios de destino que hiciste."
    >
        <HistorialDestinos
            :cambios="actividad.data"
            mostrar-producto
            titulo-vacio="Aún no has realizado cambios"
            descripcion-vacio="Aquí aparecerán los cambios de destino que hagas."
        />
    </Panel>
</template>
