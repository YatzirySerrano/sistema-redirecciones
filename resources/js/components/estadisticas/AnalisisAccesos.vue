<script setup lang="ts">
import {
    CalendarCheck,
    CalendarDays,
    CircleAlert,
    Clock,
    MousePointerClick,
    Smartphone,
    UsersRound,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import TarjetaIndicador from '@/components/comun/TarjetaIndicador.vue';
import GraficaBarras from '@/components/graficas/GraficaBarras.vue';
import GraficaColumnas from '@/components/graficas/GraficaColumnas.vue';
import GraficaDona from '@/components/graficas/GraficaDona.vue';
import GraficaLineas from '@/components/graficas/GraficaLineas.vue';
import type { SerieLinea } from '@/components/graficas/GraficaLineas.vue';
import MapaCalor from '@/components/graficas/MapaCalor.vue';
import TarjetaGrafica from '@/components/graficas/TarjetaGrafica.vue';
import {
    coberturaVisitantes,
    fechaCorta,
    numero,
    visitantes,
} from '@/lib/formato';
import type { Agrupacion, Canal } from '@/lib/graficas';
import {
    agrupacionSugerida,
    agrupar,
    CANALES,
    diaPico,
    ORDEN_CANALES,
    PALETA,
} from '@/lib/graficas';
import { cn } from '@/lib/utils';
import type { AnalisisAccesos, Barra, ElementoDesglose } from '@/types';

/**
 * Análisis visual de las interacciones de un periodo. Lo comparten el
 * cliente (su negocio) y DASTI (toda la plataforma o un negocio); la página
 * aporta los filtros y el alcance, y el servidor los datos ya agregados.
 *
 * «Interacción» = un acceso registrado a un enlace permanente (escaneo QR o
 * lectura NFC) que llegó a su destino, sin bots identificados. No equivale a
 * personas, ventas ni clientes nuevos.
 */
const props = withDefaults(
    defineProps<{
        analisis: AnalisisAccesos;
        canal?: string;
        productoFiltrado?: boolean;
        mostrarNegocio?: boolean;
        enlaceProducto?: (id: number) => string;
    }>(),
    { canal: '', productoFiltrado: false, mostrarNegocio: false },
);

const a = computed(() => props.analisis);
const total = computed(() => a.value.totales.total);
const dias = computed(() => a.value.serie.length);

// ---- Evolución ----
const agrupacion = ref<Agrupacion>(agrupacionSugerida(dias.value));
const modo = ref<'total' | 'canales'>('total');

watch(dias, (d) => {
    agrupacion.value = agrupacionSugerida(d);
});

const grupos = computed(() => agrupar(a.value.serie, agrupacion.value));
const canalesConDatos = computed(() =>
    ORDEN_CANALES.filter((c) => a.value.serie.some((p) => p[c] > 0)),
);

const series = computed<SerieLinea[]>(() => {
    if (modo.value === 'canales' && canalesConDatos.value.length > 0) {
        return canalesConDatos.value.map((c) => ({
            clave: c,
            nombre: CANALES[c].etiqueta,
            color: CANALES[c].color,
            valores: grupos.value.map((g) => g[c]),
        }));
    }

    const lista: SerieLinea[] = [
        {
            clave: 'actual',
            nombre: 'Este periodo',
            color: 'var(--primary)',
            valores: grupos.value.map((g) => g.total),
            area: true,
        },
    ];

    // El periodo anterior solo se superpone día con día (misma duración).
    if (
        agrupacion.value === 'dia' &&
        a.value.serie_anterior.length === a.value.serie.length &&
        a.value.anterior.total > 0
    ) {
        lista.push({
            clave: 'anterior',
            nombre: 'Periodo anterior',
            color: 'var(--muted-foreground)',
            valores: a.value.serie_anterior,
            discontinua: true,
        });
    }

    return lista;
});

const AGRUPACIONES: { clave: Agrupacion; etiqueta: string; minimo: number }[] =
    [
        { clave: 'dia', etiqueta: 'Día', minimo: 1 },
        { clave: 'semana', etiqueta: 'Semana', minimo: 8 },
        { clave: 'mes', etiqueta: 'Mes', minimo: 32 },
    ];

// ---- Canales ----
const segmentosCanal = computed(() =>
    ORDEN_CANALES.map((c) => ({
        clave: c,
        etiqueta: CANALES[c].etiqueta,
        color: CANALES[c].color,
        descripcion: CANALES[c].descripcion,
        total: a.value.canales.find((x) => x.clave === c)?.total ?? 0,
    })).filter((s) => s.total > 0 || s.clave !== 'combinado'),
);

// ---- Productos ----
const barrasProductos = computed<Barra[]>(() =>
    a.value.productos_top.map((p) => ({
        clave: p.id,
        etiqueta: p.nombre,
        detalle: props.mostrarNegocio ? `${p.negocio} · ${p.tipo}` : p.tipo,
        total: p.total,
        href: props.enlaceProducto?.(p.id),
        partes: ORDEN_CANALES.map((c: Canal) => ({
            clave: c,
            etiqueta: CANALES[c].corta,
            valor: p[c],
            color: CANALES[c].color,
        })),
    })),
);

// ---- Distribuciones ----
const columnasDias = computed(() =>
    a.value.dias_semana.map((d) => ({
        clave: d.dia,
        eje: d.etiqueta.slice(0, 3),
        titulo: d.etiqueta,
        valor: d.promedio,
        detalle: `${numero(d.total)} en ${d.dias} ${d.dias === 1 ? 'día' : 'días'}`,
    })),
);
const formatoPromedio = (v: number) =>
    v.toLocaleString('es-MX', { maximumFractionDigits: 1 });

const columnasHoras = computed(() =>
    a.value.horario.por_hora.map((v, h) => ({
        clave: h,
        eje: h % 3 === 0 ? String(h) : '',
        titulo: `De ${String(h).padStart(2, '0')}:00 a ${String(h).padStart(2, '0')}:59`,
        valor: v,
    })),
);
const vistaHorario = ref<'mapa' | 'horas'>('mapa');

const segmentos = (lista: ElementoDesglose[]) =>
    lista.map((e, i) => ({
        clave: e.clave,
        etiqueta: e.etiqueta,
        total: e.total,
        color: PALETA[i % PALETA.length],
    }));
const totalDetalle = computed(() =>
    a.value.dispositivos.reduce((s, d) => s + d.total, 0),
);

const pico = computed(() => diaPico(a.value.serie));
const sinRedirigir = computed(() =>
    a.value.sin_redirigir.reduce((s, x) => s + x.total, 0),
);

const notaDetalle = computed(() => {
    const c = a.value.cobertura;

    if (c.detalle_desde === null) {
        return `Los registros detallados se conservan ${c.retencion_dias} días; este periodo ya no los tiene.`;
    }

    return c.detalle_parcial
        ? `Solo desde el ${fechaCorta(c.detalle_desde)}: los registros detallados se conservan ${c.retencion_dias} días.`
        : null;
});
const sinDetalle = computed(() => a.value.cobertura.detalle_desde === null);
</script>

<template>
    <div class="space-y-6">
        <!-- Indicadores principales -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <TarjetaIndicador
                etiqueta="Interacciones"
                :valor="numero(total)"
                :icono="MousePointerClick"
                :comparacion="{ actual: total, anterior: a.anterior.total }"
                :chispa="
                    a.serie.length > 1 ? a.serie.map((p) => p.total) : undefined
                "
                ayuda="Escaneos de QR y lecturas NFC que llegaron a su destino. Una misma persona puede generar varias; se excluyen los accesos automatizados identificados."
            />
            <TarjetaIndicador
                etiqueta="Promedio diario"
                :valor="numero(a.totales.promedio_diario)"
                :icono="CalendarDays"
                :comparacion="{
                    actual: a.totales.promedio_diario,
                    anterior: a.anterior.promedio_diario,
                }"
                :detalle="`${dias} ${dias === 1 ? 'día' : 'días'} en el periodo`"
            />
            <TarjetaIndicador
                etiqueta="Día con más actividad"
                :valor="pico ? numero(pico.total) : '—'"
                :icono="CalendarCheck"
                :detalle="
                    pico
                        ? fechaCorta(pico.fecha)
                        : 'Sin interacciones en el periodo'
                "
            />
            <TarjetaIndicador
                etiqueta="Dispositivos distintos"
                :valor="visitantes(a.totales)"
                :icono="UsersRound"
                :detalle="
                    coberturaVisitantes(a.totales) ??
                    'Estimación sumada día por día'
                "
                :ayuda="`Cuenta combinaciones distintas de conexión y navegador por día, de forma anónima. No identifica personas: alguien que vuelve otro día cuenta de nuevo y varias personas en la misma red pueden contar como una. Se conserva ${a.cobertura.huella_dias} días.`"
            />
        </div>

        <div
            v-if="sinRedirigir > 0"
            class="flex items-start gap-3 rounded-xl border border-warning/30 bg-warning-soft p-4 text-warning"
            role="status"
        >
            <CircleAlert class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <div class="min-w-0 text-sm">
                <p class="font-semibold">
                    {{ numero(sinRedirigir) }}
                    {{
                        sinRedirigir === 1
                            ? 'acceso no llegó'
                            : 'accesos no llegaron'
                    }}
                    a un destino
                </p>
                <p class="opacity-90">
                    {{
                        a.sin_redirigir
                            .map((x) => `${x.etiqueta}: ${numero(x.total)}`)
                            .join(' · ')
                    }}. No se cuentan como interacciones.
                </p>
            </div>
        </div>

        <!-- Evolución y canales -->
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <TarjetaGrafica
                class="xl:col-span-2"
                titulo="Evolución de interacciones"
                :descripcion="
                    modo === 'canales'
                        ? 'Compara la actividad por NFC y por QR a lo largo del periodo.'
                        : 'Cómo cambió la actividad en el periodo; la línea punteada es el periodo anterior de igual duración.'
                "
                :vacio="total === 0"
                mensaje-vacio="Aún no hay interacciones en este periodo. La gráfica aparecerá con el primer escaneo o lectura."
            >
                <template #acciones>
                    <div
                        class="inline-flex rounded-lg border bg-background p-0.5"
                        role="group"
                        aria-label="Series"
                    >
                        <button
                            v-for="m in [
                                { clave: 'total', etiqueta: 'Total' },
                                { clave: 'canales', etiqueta: 'Por canal' },
                            ] as const"
                            :key="m.clave"
                            type="button"
                            :aria-pressed="modo === m.clave"
                            :class="
                                cn(
                                    'h-7 rounded-md px-2.5 text-xs font-medium text-muted-foreground transition hover:text-foreground',
                                    modo === m.clave &&
                                        'bg-muted text-foreground shadow-xs',
                                )
                            "
                            @click="modo = m.clave"
                        >
                            {{ m.etiqueta }}
                        </button>
                    </div>
                    <div
                        class="inline-flex rounded-lg border bg-background p-0.5"
                        role="group"
                        aria-label="Agrupar por"
                    >
                        <button
                            v-for="g in AGRUPACIONES.filter(
                                (x) => dias >= x.minimo,
                            )"
                            :key="g.clave"
                            type="button"
                            :aria-pressed="agrupacion === g.clave"
                            :class="
                                cn(
                                    'h-7 rounded-md px-2.5 text-xs font-medium text-muted-foreground transition hover:text-foreground',
                                    agrupacion === g.clave &&
                                        'bg-muted text-foreground shadow-xs',
                                )
                            "
                            @click="agrupacion = g.clave"
                        >
                            {{ g.etiqueta }}
                        </button>
                    </div>
                </template>
                <GraficaLineas
                    :puntos="grupos"
                    :series="series"
                    :etiqueta-tabla="
                        agrupacion === 'dia'
                            ? 'Día'
                            : agrupacion === 'semana'
                              ? 'Semana'
                              : 'Mes'
                    "
                />
            </TarjetaGrafica>

            <TarjetaGrafica
                titulo="NFC frente a QR"
                :descripcion="
                    canal
                        ? 'Estás viendo un solo canal; quita el filtro para comparar.'
                        : 'Con qué tecnología se abrieron tus enlaces.'
                "
                :vacio="total === 0"
                mensaje-vacio="Sin interacciones para comparar canales."
                ayuda="El canal se deduce del enlace: cada tarjeta NFC, código QR o reverso QR tiene el suyo. En las tarjetas QR + NFC con un solo enlace compartido no es posible saber cuál se usó, por eso se muestran aparte."
            >
                <GraficaDona
                    :segmentos="segmentosCanal"
                    centro="Interacciones"
                />
            </TarjetaGrafica>
        </div>

        <!-- Productos y días -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <TarjetaGrafica
                v-if="!productoFiltrado"
                titulo="Productos con más actividad"
                descripcion="Qué tarjetas y códigos se usan más, con su reparto por canal."
                :vacio="barrasProductos.length === 0"
                mensaje-vacio="Ningún producto tuvo interacciones en este periodo."
            >
                <GraficaBarras
                    :barras="barrasProductos"
                    unidad="interacciones"
                />
            </TarjetaGrafica>
            <TarjetaGrafica
                titulo="Actividad por día de la semana"
                descripcion="Promedio de interacciones de cada día; útil para saber cuándo promover tus tarjetas."
                ayuda="Se promedia entre las veces que aparece cada día en el periodo, para no favorecer a los días que se repiten más."
                :vacio="total === 0"
                :class="productoFiltrado ? 'lg:col-span-2' : undefined"
            >
                <GraficaColumnas
                    :columnas="columnasDias"
                    :formato="formatoPromedio"
                    unidad="interacciones en promedio"
                />
            </TarjetaGrafica>
        </div>

        <!-- Horario y dispositivos -->
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <TarjetaGrafica
                class="xl:col-span-2"
                titulo="Actividad por horario"
                descripcion="En qué días y horas se usan más tus productos (hora del centro de México)."
                :nota="notaDetalle"
                :vacio="sinDetalle || a.horario.total === 0"
                :icono-vacio="Clock"
                :mensaje-vacio="
                    sinDetalle
                        ? 'El detalle por hora de este periodo ya no se conserva. Elige un periodo más reciente.'
                        : 'Sin interacciones con detalle de horario en este periodo.'
                "
            >
                <template #acciones>
                    <div
                        class="inline-flex rounded-lg border bg-background p-0.5"
                        role="group"
                        aria-label="Vista del horario"
                    >
                        <button
                            v-for="v in [
                                { clave: 'mapa', etiqueta: 'Día y hora' },
                                { clave: 'horas', etiqueta: 'Por hora' },
                            ] as const"
                            :key="v.clave"
                            type="button"
                            :aria-pressed="vistaHorario === v.clave"
                            :class="
                                cn(
                                    'h-7 rounded-md px-2.5 text-xs font-medium text-muted-foreground transition hover:text-foreground',
                                    vistaHorario === v.clave &&
                                        'bg-muted text-foreground shadow-xs',
                                )
                            "
                            @click="vistaHorario = v.clave"
                        >
                            {{ v.etiqueta }}
                        </button>
                    </div>
                </template>
                <MapaCalor
                    v-if="vistaHorario === 'mapa'"
                    :celdas="a.horario.celdas"
                />
                <GraficaColumnas v-else :columnas="columnasHoras" :alto="160" />
            </TarjetaGrafica>

            <TarjetaGrafica
                titulo="Dispositivos"
                descripcion="Desde qué tipo de equipo se abrieron los enlaces."
                ayuda="Se deduce de forma aproximada del navegador; no se guarda información que identifique al dispositivo."
                :nota="notaDetalle"
                :vacio="sinDetalle || totalDetalle === 0"
                :icono-vacio="Smartphone"
                :mensaje-vacio="
                    sinDetalle
                        ? 'El detalle de dispositivos de este periodo ya no se conserva.'
                        : 'Sin interacciones en este periodo.'
                "
            >
                <GraficaDona
                    :segmentos="segmentos(a.dispositivos)"
                    centro="Con detalle"
                    :tamano="140"
                />
            </TarjetaGrafica>
        </div>

        <!-- Navegadores y sistemas -->
        <div
            v-if="!sinDetalle && totalDetalle > 0"
            class="grid grid-cols-1 gap-6 md:grid-cols-2"
        >
            <TarjetaGrafica
                titulo="Navegadores"
                descripcion="Aplicación con la que se abrió el enlace (incluye Instagram y Facebook)."
            >
                <GraficaBarras
                    :barras="
                        a.navegadores.map((e) => ({
                            clave: e.clave,
                            etiqueta: e.etiqueta,
                            total: e.total,
                        }))
                    "
                    unidad="interacciones"
                />
            </TarjetaGrafica>
            <TarjetaGrafica
                titulo="Sistemas operativos"
                descripcion="Sistema del teléfono o computadora."
            >
                <GraficaBarras
                    :barras="
                        a.sistemas.map((e) => ({
                            clave: e.clave,
                            etiqueta: e.etiqueta,
                            total: e.total,
                        }))
                    "
                    unidad="interacciones"
                />
            </TarjetaGrafica>
        </div>
    </div>
</template>
