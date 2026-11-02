<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CircleAlert,
    CircleCheck,
    Clock,
    CreditCard,
    HeartHandshake,
    ShieldCheck,
} from '@lucide/vue';
import { ref } from 'vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import InsigniaEstado from '@/components/comun/InsigniaEstado.vue';
import Panel from '@/components/comun/Panel.vue';
import NavFidelidad from '@/components/fidelidad/NavFidelidad.vue';
import type { EstadoServicio } from '@/components/fidelidad/tipos';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { fechaCorta, pesos } from '@/lib/formato';
import { contratacion } from '@/routes/cliente/fidelidad';
import { checkout } from '@/routes/cliente/fidelidad/contratacion';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Contratar Fidelidad', href: contratacion() }],
    },
});

const props = defineProps<{
    servicio: EstadoServicio;
    oferta: {
        nombre: string;
        descripcion: string | null;
        precio_centavos: number | null;
        moneda: string;
        vigencia_meses: number | null;
        no_disponible: string | null;
        puede_contratar: boolean;
    };
    retorno: 'exito' | 'cancelado' | null;
    pago: {
        id: number;
        estado: string;
        estado_etiqueta: string;
        importe_centavos: number;
        moneda: string;
    } | null;
    historial: {
        id: number;
        estado: string;
        estado_etiqueta: string;
        origen: string;
        inicia_en: string | null;
        vence_en: string | null;
    }[];
}>();

const enviando = ref(false);
const errores = ref<string | null>(null);
const contratar = () =>
    router.post(
        checkout.url(),
        {},
        {
            onStart: () => (enviando.value = true),
            onFinish: () => (enviando.value = false),
            onError: (e) =>
                (errores.value = e.servicio ?? Object.values(e)[0] ?? null),
        },
    );

const refrescar = () =>
    router.reload({ only: ['pago', 'servicio', 'historial'] });
const estadoInsignia = (e: string) =>
    e === 'activa' || e === 'pagado'
        ? 'activo'
        : e === 'pendiente'
          ? 'pendiente'
          : 'suspendido';
void props;
</script>

<template>
    <Head title="Contratar Fidelidad" />

    <EncabezadoPagina
        titulo="Fidelidad DASTI"
        descripcion="Tarjetas de sellos digitales para premiar a tus clientes frecuentes."
        :icono="HeartHandshake"
    />

    <NavFidelidad :servicio="servicio" />

    <div
        v-if="retorno === 'exito' && pago"
        class="flex items-start gap-3 rounded-xl border p-4 text-sm"
        :class="
            pago.estado === 'pagado'
                ? 'border-success/30 bg-success-soft text-success'
                : 'border-warning/30 bg-warning-soft text-warning'
        "
        role="status"
    >
        <CircleCheck
            v-if="pago.estado === 'pagado'"
            class="mt-0.5 size-5 shrink-0"
        />
        <Clock v-else class="mt-0.5 size-5 shrink-0" />
        <div>
            <p class="font-semibold">
                {{
                    pago.estado === 'pagado'
                        ? 'Pago confirmado. Fidelidad ya está activo.'
                        : 'Estamos confirmando tu pago con el procesador.'
                }}
            </p>
            <p v-if="pago.estado !== 'pagado'" class="mt-1">
                El servicio se activa automáticamente cuando el procesador
                confirma el cobro (suele tardar unos segundos).
                <button type="button" class="underline" @click="refrescar">
                    Actualizar estado
                </button>
            </p>
        </div>
    </div>
    <div
        v-else-if="retorno === 'cancelado'"
        class="rounded-xl border bg-muted/40 p-4 text-sm"
        role="status"
    >
        Cancelaste el pago. No se realizó ningún cargo; puedes intentarlo cuando
        quieras.
    </div>

    <div
        class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]"
    >
        <Panel titulo="Qué incluye">
            <ul class="space-y-3 text-sm">
                <li class="flex gap-2">
                    <CircleCheck class="mt-0.5 size-4 shrink-0 text-primary" />
                    Campañas como «8 visitas = 1 café» con reglas que evitan
                    abusos.
                </li>
                <li class="flex gap-2">
                    <CircleCheck class="mt-0.5 size-4 shrink-0 text-primary" />
                    Mostrador para tu personal: identifica con tarjeta NFC, QR o
                    búsqueda, y confirma visitas y canjes.
                </li>
                <li class="flex gap-2">
                    <CircleCheck class="mt-0.5 size-4 shrink-0 text-primary" />
                    Si un cliente pierde su tarjeta, conserva su progreso.
                </li>
                <li class="flex gap-2">
                    <CircleCheck class="mt-0.5 size-4 shrink-0 text-primary" />
                    Página de progreso para tus clientes desde el teléfono, sin
                    contraseñas.
                </li>
                <li class="flex gap-2">
                    <ShieldCheck class="mt-0.5 size-4 shrink-0 text-primary" />
                    Tus redirecciones QR/NFC siguen funcionando igual, contrates
                    o no.
                </li>
            </ul>
        </Panel>

        <Panel titulo="Contratación">
            <p class="text-sm text-muted-foreground">Estado actual</p>
            <p class="mt-1">
                <InsigniaEstado
                    :estado="
                        servicio.activo
                            ? 'activo'
                            : servicio.estado === 'vencida' ||
                                servicio.estado === 'restringida'
                              ? 'pendiente'
                              : 'suspendido'
                    "
                    :etiqueta="
                        servicio.activo
                            ? 'Activo'
                            : servicio.estado === 'vencida'
                              ? 'Vencido'
                              : servicio.estado === 'restringida'
                                ? 'En revisión'
                                : servicio.estado === 'suspendida'
                                  ? 'Suspendido'
                                  : 'Sin contratar'
                    "
                />
                <span
                    v-if="servicio.vence_en"
                    class="ml-2 text-xs text-muted-foreground"
                    >vence {{ fechaCorta(servicio.vence_en) }}</span
                >
            </p>

            <div class="mt-5 border-t pt-4">
                <p
                    v-if="oferta.precio_centavos"
                    class="text-2xl font-semibold tracking-tight"
                >
                    {{ pesos(oferta.precio_centavos) }}
                    <span class="text-sm font-normal text-muted-foreground"
                        >{{ oferta.moneda }}, pago único</span
                    >
                </p>
                <p class="text-xs text-muted-foreground">
                    {{
                        oferta.vigencia_meses
                            ? `Vigencia de ${oferta.vigencia_meses} meses. Sin renovación automática.`
                            : 'Sin renovación automática.'
                    }}
                </p>
                <p
                    v-if="oferta.no_disponible"
                    class="mt-3 flex items-start gap-2 text-sm text-muted-foreground"
                >
                    <CircleAlert class="mt-0.5 size-4 shrink-0" />
                    {{ oferta.no_disponible }}
                </p>
                <Button
                    v-else-if="oferta.puede_contratar"
                    class="mt-4 h-11 w-full"
                    :disabled="enviando"
                    @click="contratar"
                >
                    <Spinner v-if="enviando" />
                    <CreditCard v-else />
                    Pagar con tarjeta
                </Button>
                <p v-else class="mt-3 text-sm text-muted-foreground">
                    Pide al responsable del negocio que realice la contratación.
                </p>
                <p
                    v-if="errores"
                    class="mt-2 text-sm text-destructive"
                    role="alert"
                >
                    {{ errores }}
                </p>
                <p class="mt-3 text-xs text-muted-foreground">
                    El pago se hace en la página segura de Stripe. DASTI no
                    recibe ni guarda los datos de tu tarjeta.
                </p>
            </div>

            <div v-if="historial.length" class="mt-5 border-t pt-4">
                <p class="text-xs font-medium text-muted-foreground">
                    Historial
                </p>
                <ul class="mt-2 space-y-2 text-sm">
                    <li
                        v-for="h in historial"
                        :key="h.id"
                        class="flex items-center justify-between gap-2"
                    >
                        <span class="text-xs"
                            >{{ fechaCorta(h.inicia_en) }} –
                            {{
                                h.vence_en
                                    ? fechaCorta(h.vence_en)
                                    : 'sin vencimiento'
                            }}
                            · {{ h.origen }}</span
                        >
                        <InsigniaEstado
                            :estado="estadoInsignia(h.estado)"
                            :etiqueta="h.estado_etiqueta"
                        />
                    </li>
                </ul>
            </div>
        </Panel>
    </div>
</template>
