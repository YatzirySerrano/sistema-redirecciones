<script setup lang="ts">
import { Download, Nfc, Palette, QrCode } from "@lucide/vue";
import { computed } from "vue";
import Ayuda from "@/components/comun/Ayuda.vue";
import EnlacePermanente from "@/components/comun/EnlacePermanente.vue";
import InsigniaEstado from "@/components/comun/InsigniaEstado.vue";
import FormularioDestino from "@/components/productos/FormularioDestino.vue";
import { Button } from "@/components/ui/button";
import { numero } from "@/lib/formato";
import { show as qrShow } from "@/routes/productos/qr";
import type { EstadisticaMedio, OpcionesQr, Producto } from "@/types";

/**
 * Producto con varios enlaces (QR + NFC con destinos independientes, o dos
 * QR): muestra cada enlace con su propio enlace permanente, destino, estado,
 * vista previa del QR y accesos, para que nunca se confunda qué URL va
 * grabada en el chip y cuál impresa en cada QR.
 */
const props = defineProps<{
    producto: Producto;
    accionDestino: (id: number) => string;
    estadisticas?: EstadisticaMedio[];
    sugerencias?: { etiqueta: string; url: string }[];
    /** Diseño guardado de cada QR (refresca la vista previa al guardarlo). */
    opcionesQr?: Record<number, OpcionesQr>;
}>();

const emit = defineEmits<{ disenar: [id: number] }>();

const enlaces = computed(() => [
    props.producto,
    ...(props.producto.enlaces_secundarios ?? []),
]);

const situacion = (p: Producto) => {
    if (props.producto.negocio?.estado === "suspendido") {
        return "negocio_suspendido";
    }

    if (p.estado === "suspendido") {
        return "producto_suspendido";
    }

    return p.url_destino ? "redirigiendo" : "sin_destino";
};

const accesos = (id: number) =>
    props.estadisticas?.find((e) => e.id === id)?.totales.total ?? null;

const ayuda = (e: Producto) => {
    if (e.medio === "nfc") {
        return "Esta es la dirección grabada en el chip NFC del frente de la tarjeta. Puedes cambiar su destino sin volver a grabar el chip.";
    }

    return e.numero_qr
        ? `Esta es la dirección impresa en el QR ${e.numero_qr}. Cada QR tiene su propio destino; puedes cambiarlo sin reimprimir.`
        : "Esta es la dirección impresa en el código QR del reverso. Puedes cambiar su destino sin reimprimir el QR.";
};

/** Huella corta del diseño: cambia la URL de la vista previa al guardarlo. */
const huella = (texto: string) =>
    [...texto]
        .reduce((h, c) => (Math.imul(31, h) + c.charCodeAt(0)) | 0, 0)
        .toString(36);

/** Vista previa SVG del QR con su diseño guardado. */
const vistaPrevia = (e: Producto) =>
    qrShow.url(e.id, {
        query: {
            formato: "svg",
            tamano: 256,
            v: huella(JSON.stringify(props.opcionesQr?.[e.id] ?? {})),
        },
    });
</script>

<template>
    <div
        class="grid gap-4 lg:grid-cols-2"
        :class="enlaces.length > 2 && '2xl:grid-cols-3'"
    >
        <section
            v-for="enlace in enlaces"
            :key="enlace.id"
            class="flex min-w-0 flex-col rounded-xl border bg-card shadow-xs"
            :aria-label="enlace.medio_etiqueta ?? 'Enlace'"
        >
            <header class="flex items-center gap-3 border-b px-4 py-3">
                <span
                    class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    aria-hidden="true"
                >
                    <Nfc v-if="enlace.medio === 'nfc'" class="size-4" />
                    <QrCode v-else class="size-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <h3 class="flex items-center gap-1 text-sm font-semibold">
                        {{ enlace.medio_etiqueta }}
                        <Ayuda :texto="ayuda(enlace)" />
                    </h3>
                    <p class="font-mono text-xs text-muted-foreground">
                        /r/{{ enlace.codigo }}
                    </p>
                </div>
                <InsigniaEstado :estado="situacion(enlace)" />
            </header>
            <div class="flex flex-1 flex-col gap-4 p-4">
                <div class="flex items-center gap-3">
                    <img
                        v-if="
                            enlace.medio !== 'nfc' &&
                            enlace.permisos.descargar_qr
                        "
                        :src="vistaPrevia(enlace)"
                        :alt="`Vista previa del ${enlace.medio_etiqueta ?? 'QR'}`"
                        class="size-16 shrink-0 rounded-md border bg-white p-1"
                        loading="lazy"
                        width="64"
                        height="64"
                    />
                    <EnlacePermanente
                        :url="enlace.url_permanente"
                        compacto
                        class="min-w-0 flex-1"
                    />
                </div>
                <div
                    class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs"
                >
                    <span class="text-muted-foreground">Destino actual:</span>
                    <a
                        v-if="enlace.url_destino"
                        :href="enlace.url_destino"
                        target="_blank"
                        rel="noopener noreferrer nofollow"
                        class="min-w-0 truncate font-medium text-enlace hover:underline"
                        >{{ enlace.url_destino }}</a
                    >
                    <span v-else class="text-muted-foreground italic"
                        >Sin destino configurado</span
                    >
                    <span
                        v-if="accesos(enlace.id) !== null"
                        class="ml-auto text-muted-foreground tabular-nums"
                        >{{ numero(accesos(enlace.id)) }} accesos · 30
                        días</span
                    >
                </div>
                <FormularioDestino
                    v-if="enlace.permisos.cambiar_destino"
                    :key="enlace.url_destino ?? ''"
                    :producto="enlace"
                    :accion="accionDestino(enlace.id)"
                    :obligatorio="false"
                    compacto
                    :sugerencias="sugerencias"
                />
                <div
                    v-if="
                        enlace.medio !== 'nfc' && enlace.permisos.descargar_qr
                    "
                    class="mt-auto flex flex-wrap gap-2 border-t pt-3"
                >
                    <Button
                        variant="outline"
                        size="sm"
                        type="button"
                        @click="emit('disenar', enlace.id)"
                        ><Palette /> Diseño</Button
                    >
                    <Button variant="outline" size="sm" as-child>
                        <a
                            :href="
                                qrShow.url(enlace.id, {
                                    query: { formato: 'png', descargar: 1 },
                                })
                            "
                            ><Download /> QR en PNG</a
                        >
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <a
                            :href="
                                qrShow.url(enlace.id, {
                                    query: { formato: 'svg', descargar: 1 },
                                })
                            "
                            ><Download /> QR en SVG</a
                        >
                    </Button>
                </div>
            </div>
        </section>
    </div>
</template>
