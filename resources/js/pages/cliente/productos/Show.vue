<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CircleAlert, Link2, Lock, Target } from '@lucide/vue';
import { computed } from 'vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EnlacePermanente from '@/components/comun/EnlacePermanente.vue';
import InsigniaEstado from '@/components/comun/InsigniaEstado.vue';
import InsigniaTipo from '@/components/comun/InsigniaTipo.vue';
import Panel from '@/components/comun/Panel.vue';
import GraficaArea from '@/components/graficas/GraficaArea.vue';
import DisenadorQr from '@/components/productos/DisenadorQr.vue';
import FormularioDestino from '@/components/productos/FormularioDestino.vue';
import HistorialDestinos from '@/components/productos/HistorialDestinos.vue';
import { Button } from '@/components/ui/button';
import { numero } from '@/lib/formato';
import { destino, index, show } from '@/routes/cliente/productos';
import type {
    CambioDestino,
    Coleccion,
    OpcionesQr,
    Producto,
    PuntoSerie,
    Recurso,
    TotalesEstadisticas,
} from '@/types';

const props = defineProps<{
    producto: Recurso<Producto>;
    opciones_qr: OpcionesQr;
    estadisticas: { totales: TotalesEstadisticas; serie: PuntoSerie[] } | null;
    cambios: Coleccion<CambioDestino>;
}>();

defineOptions({
    layout: (p: { producto: Recurso<Producto> }) => ({
        breadcrumbs: [
            { title: 'Mis productos', href: index() },
            { title: p.producto.data.nombre, href: show(p.producto.data.id) },
        ],
    }),
});

const p = computed(() => props.producto.data);
const muestraNfc = computed(
    () => p.value.tipo === 'nfc' || p.value.tipo === 'combinado',
);
const muestraQr = computed(
    () => p.value.tipo === 'qr' || p.value.tipo === 'combinado',
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
                <InsigniaEstado
                    :estado="p.estado"
                    :etiqueta="p.estado_etiqueta"
                />
            </div>
        </template>
    </EncabezadoPagina>

    <div
        class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]"
    >
        <div class="min-w-0 space-y-6">
            <Panel
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

            <Panel
                v-if="p.permisos.descargar_qr && muestraQr"
                titulo="Código QR"
                descripcion="Descarga tu QR para imprimirlo. Contiene el enlace permanente, así que no cambia aunque modifiques el destino."
            >
                <DisenadorQr :producto="p" :opciones="opciones_qr" />
            </Panel>

            <Panel titulo="Historial de cambios">
                <HistorialDestinos :cambios="cambios.data" />
            </Panel>
        </div>

        <aside class="min-w-0 space-y-6">
            <Panel titulo="Enlace permanente">
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
                            {{
                                numero(
                                    estadisticas.totales.visitantes_estimados,
                                )
                            }}
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
