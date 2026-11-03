<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    ChartNoAxesColumn,
    Download,
    Eye,
    Inbox,
    Info,
    MousePointerClick,
    Percent,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import Panel from '@/components/comun/Panel.vue';
import SelectorPeriodo from '@/components/comun/SelectorPeriodo.vue';
import TarjetaIndicador from '@/components/comun/TarjetaIndicador.vue';
import GraficaArea from '@/components/graficas/GraficaArea.vue';
import GraficaBarras from '@/components/graficas/GraficaBarras.vue';
import { Button } from '@/components/ui/button';
import { useFiltros } from '@/composables/useFiltros';
import { fechaCorta, numero } from '@/lib/formato';
import { inicio } from '@/routes/cliente/paginas';
import { exportar, index } from '@/routes/cliente/paginas/estadisticas';
import type { Barra, ElementoDesglose } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Página de enlaces', href: inicio() },
            { title: 'Estadísticas', href: index() },
        ],
    },
});

type Totales = {
    visitas: number;
    clics: number;
    contactos: number;
    ctr: number;
    bots: number;
};

const props = defineProps<{
    filtros: { desde: string; hasta: string };
    totales: Totales & {
        visitantes_estimados: number | null;
        visitantes_parcial: boolean;
    };
    previo: Totales;
    serie: { fecha: string; visitas: number; clics: number }[];
    elementos: {
        id: string;
        nombre: string;
        tipo: string;
        clics: number;
        ctr: number;
    }[];
    dispositivos: ElementoDesglose[];
    referentes: ElementoDesglose[];
    subpaginas: ElementoDesglose[];
    horas: { hora: number; total: number }[];
}>();

const { filtros } = useFiltros(index().url, { ...props.filtros });

/** Comparación con el periodo anterior de la misma duración. */
const comparar = (actual: number, previo: number) => {
    if (previo === 0) {
        return actual === 0
            ? 'Sin datos en el periodo anterior'
            : 'Sin datos en el periodo anterior para comparar';
    }

    const cambio = Math.round(((actual - previo) / previo) * 100);

    return cambio === 0
        ? 'Igual que el periodo anterior'
        : `${cambio > 0 ? '+' : ''}${cambio} % frente al periodo anterior`;
};

const serieVisitas = computed(() =>
    props.serie.map((p) => ({ fecha: p.fecha, total: p.visitas })),
);
const serieClics = computed(() =>
    props.serie.map((p) => ({ fecha: p.fecha, total: p.clics })),
);
const barrasElementos = computed<Barra[]>(() =>
    props.elementos.slice(0, 15).map((e) => ({
        clave: e.id,
        etiqueta: e.nombre,
        detalle: `${e.ctr} % de las visitas`,
        total: e.clics,
    })),
);
const aBarras = (lista: ElementoDesglose[]): Barra[] =>
    lista.map((e) => ({
        clave: e.clave,
        etiqueta: e.etiqueta,
        total: e.total,
    }));
const barrasHoras = computed<Barra[]>(() =>
    props.horas
        .filter((h) => h.total > 0)
        .map((h) => ({
            clave: h.hora,
            etiqueta: `${String(h.hora).padStart(2, '0')}:00 – ${String(h.hora).padStart(2, '0')}:59`,
            total: h.total,
        })),
);
const urlCsv = computed(
    () =>
        exportar({ query: { desde: filtros.desde, hasta: filtros.hasta } }).url,
);
const visitantes = computed(() =>
    props.totales.visitantes_estimados === null
        ? 'No disponible'
        : numero(props.totales.visitantes_estimados),
);
</script>

<template>
    <Head title="Estadísticas de tu página" />

    <EncabezadoPagina
        titulo="Estadísticas de tu página"
        :descripcion="`Del ${fechaCorta(filtros.desde)} al ${fechaCorta(filtros.hasta)}.`"
        :icono="ChartNoAxesColumn"
    >
        <template #acciones>
            <Button variant="outline" as-child class="h-11">
                <a :href="urlCsv"><Download /> Exportar CSV</a>
            </Button>
        </template>
    </EncabezadoPagina>

    <div class="rounded-xl border bg-card p-3 shadow-xs">
        <SelectorPeriodo
            v-model:desde="filtros.desde"
            v-model:hasta="filtros.hasta"
        />
    </div>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
        <TarjetaIndicador
            etiqueta="Visitas"
            :valor="numero(totales.visitas)"
            :icono="Eye"
            :detalle="comparar(totales.visitas, previo.visitas)"
        />
        <TarjetaIndicador
            etiqueta="Clics"
            :valor="numero(totales.clics)"
            :icono="MousePointerClick"
            :detalle="comparar(totales.clics, previo.clics)"
        />
        <TarjetaIndicador
            etiqueta="Tasa de clics"
            :valor="`${totales.ctr} %`"
            :icono="Percent"
            detalle="Clics por cada 100 visitas"
            ayuda="Puede superar 100 % si una misma visita toca varios enlaces."
        />
        <TarjetaIndicador
            etiqueta="Mensajes recibidos"
            :valor="numero(totales.contactos)"
            :icono="Inbox"
            :detalle="comparar(totales.contactos, previo.contactos)"
            ayuda="Envíos reales del formulario de contacto: la única conversión que medimos."
        />
        <TarjetaIndicador
            etiqueta="Visitantes estimados"
            :valor="visitantes"
            :icono="UsersRound"
            :detalle="
                totales.visitantes_parcial
                    ? 'Solo cubre los últimos 30 días del periodo'
                    : 'Aproximación por dispositivo y día'
            "
            ayuda="Contamos dispositivos distintos por día con una huella anónima que se borra a los 30 días. No son personas exactas."
        />
    </div>

    <EstadoVacio
        v-if="totales.visitas === 0 && totales.clics === 0"
        class="rounded-xl border bg-card"
        :icono="ChartNoAxesColumn"
        titulo="Sin visitas en este periodo"
        descripcion="Comparte tu dirección o prueba con un periodo más amplio."
    />

    <template v-else>
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <Panel titulo="Visitas por día"
                ><GraficaArea :serie="serieVisitas" etiqueta="Visitas"
            /></Panel>
            <Panel titulo="Clics por día"
                ><GraficaArea :serie="serieClics" etiqueta="Clics"
            /></Panel>
        </div>

        <Panel
            titulo="Enlaces más usados"
            descripcion="Clics por elemento y porcentaje respecto a las visitas."
        >
            <GraficaBarras
                :barras="barrasElementos"
                unidad="clics"
                vacio="Aún no hay clics en este periodo."
            />
        </Panel>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <Panel
                titulo="¿De dónde llegan?"
                descripcion="Sitio desde el que abrieron tu página (solo el dominio)."
            >
                <GraficaBarras :barras="aBarras(referentes)" unidad="visitas" />
            </Panel>
            <Panel titulo="Dispositivos">
                <GraficaBarras
                    :barras="aBarras(dispositivos)"
                    unidad="visitas"
                />
            </Panel>
            <Panel v-if="subpaginas.length > 1" titulo="Páginas más visitadas">
                <GraficaBarras :barras="aBarras(subpaginas)" unidad="visitas" />
            </Panel>
            <Panel titulo="Horario de visitas">
                <GraficaBarras :barras="barrasHoras" unidad="visitas" />
            </Panel>
        </div>
    </template>

    <p class="flex gap-2 text-xs text-muted-foreground">
        <Info class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
        <span>
            Las visitas se cuentan al abrir la página y los clics al tocar un
            elemento; se excluyen los accesos automatizados identificados ({{
                numero(totales.bots)
            }}
            en este periodo) y las precargas de los navegadores. No guardamos
            direcciones IP ni las direcciones completas de procedencia: solo el
            dominio. Dispositivos, procedencia y horarios cubren el periodo de
            conservación de los registros detallados; los totales diarios se
            conservan.
        </span>
    </p>
</template>
