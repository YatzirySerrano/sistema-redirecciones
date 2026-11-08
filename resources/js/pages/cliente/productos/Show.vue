<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import { ArrowLeft, CircleAlert, Link2, Lock, Target } from "@lucide/vue";
import { computed } from "vue";
import EncabezadoPagina from "@/components/comun/EncabezadoPagina.vue";
import EnlacePermanente from "@/components/comun/EnlacePermanente.vue";
import InsigniaEstado from "@/components/comun/InsigniaEstado.vue";
import InsigniaTipo from "@/components/comun/InsigniaTipo.vue";
import Panel from "@/components/comun/Panel.vue";
import GraficaArea from "@/components/graficas/GraficaArea.vue";
import CambiarCantidadQr from "@/components/productos/CambiarCantidadQr.vue";
import DisenadorQr from "@/components/productos/DisenadorQr.vue";
import EnlacesPorMedio from "@/components/productos/EnlacesPorMedio.vue";
import FormularioDestino from "@/components/productos/FormularioDestino.vue";
import HistorialDestinos from "@/components/productos/HistorialDestinos.vue";
import { Button } from "@/components/ui/button";
import { numero, visitantes } from "@/lib/formato";
import { enlacesQr } from "@/lib/productos";
import { cantidadQr, destino, index, show } from "@/routes/cliente/productos";
import type {
    EstadisticaMedio,
    CambioDestino,
    Coleccion,
    OpcionesQr,
    Producto,
    PuntoSerie,
    Recurso,
    TotalesEstadisticas,
} from "@/types";

const props = defineProps<{
    producto: Recurso<Producto>;
    opciones_qr: OpcionesQr;
    /** Diseño guardado de cada QR, por id de enlace. */
    opciones_qr_enlaces?: Record<number, OpcionesQr>;
    estadisticas: { totales: TotalesEstadisticas; serie: PuntoSerie[] } | null;
    cambios: Coleccion<CambioDestino>;
    estadisticas_medios: EstadisticaMedio[];
    destinos_pagina?: { etiqueta: string; url: string }[];
}>();

defineOptions({
    layout: (p: { producto: Recurso<Producto> }) => ({
        breadcrumbs: [
            { title: "Mis productos", href: index() },
            { title: p.producto.data.nombre, href: show(p.producto.data.id) },
        ],
    }),
});

const p = computed(() => props.producto.data);
const independiente = computed(
    () => p.value.modalidad_destino === "independiente",
);
/** Enlaces impresos como QR: el reverso en los combinados independientes; QR 1 y QR 2 si lleva dos. */
const qrs = computed(() => enlacesQr(p.value));
const dosQr = computed(() => p.value.cantidad_qr === 2);
const opcionesDe = (id: number) =>
    props.opciones_qr_enlaces?.[id] ?? props.opciones_qr;
const descripcionEnlaces = computed(() => {
    if (dosQr.value) {
        return p.value.tipo === "combinado"
            ? "Tu tarjeta tiene tres enlaces: el NFC, el QR 1 y el QR 2. Cada uno puede llevar a una página distinta."
            : "Tu producto tiene dos QR. Cada uno puede llevar a una página distinta y tener su propio diseño.";
    }

    return "Tu tarjeta tiene dos enlaces: el NFC del frente y el QR del reverso. Cada uno puede llevar a una página distinta.";
});
const irADiseno = (id: number) => {
    document
        .getElementById(`diseno-qr-${id}`)
        ?.scrollIntoView({ behavior: "smooth", block: "start" });
};
const muestraNfc = computed(
    () => p.value.tipo === "nfc" || p.value.tipo === "combinado",
);
const muestraQr = computed(
    () => p.value.tipo === "qr" || p.value.tipo === "combinado",
);
</script>

<template>
    <Head :title="`Editar destino · ${p.nombre}`" />

    <EncabezadoPagina
        :titulo="p.nombre"
        descripcion="Elige a dónde lleva este producto cuando alguien lo escanea o lo acerca a su teléfono."
    >
        <template #antes>
            <Link
                :href="index()"
                class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-4" /> Mis productos
            </Link>
        </template>
        <template #detalle>
            <div class="flex flex-wrap items-center gap-2 pt-1">
                <InsigniaTipo :tipo="p.tipo" :etiqueta="p.tipo_etiqueta" />
                <span
                    v-if="dosQr"
                    class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                    >2 QR</span
                >
                <InsigniaEstado
                    :estado="p.estado"
                    :etiqueta="p.estado_etiqueta"
                />
            </div>
        </template>
        <template v-if="p.permisos.cambiar_cantidad_qr" #acciones>
            <CambiarCantidadQr :producto="p" :accion="cantidadQr.url(p.id)" />
        </template>
    </EncabezadoPagina>

    <div
        class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]"
    >
        <div class="min-w-0 space-y-6">
            <Panel
                v-if="independiente"
                :titulo="
                    dosQr ? 'Destinos de tus QR' : 'Destinos de tu tarjeta'
                "
                :descripcion="descripcionEnlaces"
            >
                <EnlacesPorMedio
                    :producto="p"
                    :accion-destino="(id: number) => destino.url(id)"
                    :estadisticas="estadisticas_medios"
                    :sugerencias="destinos_pagina ?? []"
                    :opciones-qr="opciones_qr_enlaces"
                    @disenar="irADiseno"
                />
            </Panel>
            <Panel
                v-else
                titulo="Destino"
                descripcion="La página que se abre al usar este producto."
            >
                <div
                    class="mb-5 flex items-start gap-3 rounded-lg border bg-muted/40 p-3"
                >
                    <Target
                        class="mt-0.5 size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <div class="min-w-0 text-sm">
                        <p class="text-xs text-muted-foreground">
                            Destino actual
                        </p>
                        <a
                            v-if="p.url_destino"
                            :href="p.url_destino"
                            target="_blank"
                            rel="noopener noreferrer nofollow"
                            class="block font-medium break-all hover:text-primary hover:underline"
                            >{{ p.url_destino }}</a
                        >
                        <p v-else class="font-medium text-warning">
                            Sin destino configurado
                        </p>
                    </div>
                </div>

                <FormularioDestino
                    v-if="p.permisos.cambiar_destino"
                    :key="p.id"
                    :producto="p"
                    :accion="destino.url(p.id)"
                    obligatorio
                    :sugerencias="destinos_pagina ?? []"
                />
                <div
                    v-else
                    class="flex gap-3 rounded-lg bg-destructive/10 p-4 text-sm text-destructive"
                    role="alert"
                >
                    <CircleAlert class="mt-0.5 size-4 shrink-0" />
                    <p>
                        Este producto está <strong>suspendido</strong>: su
                        enlace muestra «no disponible» y no es posible cambiar
                        el destino. Contacta a DASTI para reactivarlo.
                    </p>
                </div>
            </Panel>

            <template v-if="muestraQr">
                <Panel
                    v-for="qr in qrs.filter((e) => e.permisos.descargar_qr)"
                    :id="`diseno-qr-${qr.id}`"
                    :key="qr.id"
                    class="scroll-mt-20"
                    :titulo="
                        qr.numero_qr
                            ? `Diseño del ${qr.medio_etiqueta}`
                            : independiente
                              ? 'Código QR del reverso'
                              : 'Código QR'
                    "
                    :descripcion="
                        qr.numero_qr
                            ? `Personaliza y descarga el ${qr.medio_etiqueta} (/r/${qr.codigo}). Su diseño es independiente del otro QR.`
                            : 'Descarga tu QR para imprimirlo. Contiene el enlace permanente, así que no cambia aunque modifiques el destino.'
                    "
                >
                    <DisenadorQr
                        :key="qr.id"
                        :producto="qr"
                        :opciones="opcionesDe(qr.id)"
                    />
                </Panel>
            </template>

            <Panel titulo="Historial de cambios">
                <HistorialDestinos :cambios="cambios.data" />
            </Panel>
        </div>

        <aside class="min-w-0 space-y-6">
            <Panel v-if="!independiente" titulo="Enlace permanente">
                <div class="space-y-3">
                    <EnlacePermanente :url="p.url_permanente" />
                    <p class="flex gap-2 text-xs text-muted-foreground">
                        <Lock
                            class="mt-0.5 size-3.5 shrink-0"
                            aria-hidden="true"
                        />
                        <span>
                            Es la dirección que está
                            <template v-if="muestraQr && muestraNfc"
                                >impresa en tu QR y grabada en tu tarjeta
                                NFC</template
                            >
                            <template v-else-if="muestraNfc"
                                >grabada en tu tarjeta NFC</template
                            >
                            <template v-else>impresa en tu QR</template>. Nunca
                            cambia: solo cambia el destino al que redirige.
                        </span>
                    </p>
                </div>
            </Panel>

            <Panel v-if="estadisticas" titulo="Últimos 30 días">
                <dl class="mb-4 grid grid-cols-2 gap-3">
                    <div class="rounded-lg bg-muted/50 p-3">
                        <dt class="text-xs text-muted-foreground">Accesos</dt>
                        <dd class="text-xl font-semibold tabular-nums">
                            {{ numero(estadisticas.totales.total) }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-muted/50 p-3">
                        <dt class="text-xs text-muted-foreground">
                            Visitantes estimados
                        </dt>
                        <dd class="text-xl font-semibold tabular-nums">
                            {{ visitantes(estadisticas.totales) }}
                        </dd>
                    </div>
                </dl>
                <GraficaArea
                    :serie="estadisticas.serie"
                    etiqueta="Accesos"
                    :alto="180"
                />
                <p class="mt-2 text-xs text-muted-foreground">
                    <Link2 class="mr-1 inline size-3" aria-hidden="true" />Total
                    histórico: {{ numero(p.total_accesos) }} accesos.
                </p>
            </Panel>

            <Button variant="outline" as-child class="w-full">
                <Link :href="index()"
                    ><ArrowLeft /> Volver a mis productos</Link
                >
            </Button>
        </aside>
    </div>
</template>
