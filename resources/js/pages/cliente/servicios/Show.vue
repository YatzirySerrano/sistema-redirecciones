<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    CircleAlert,
    CircleCheck,
    Clock,
    CreditCard,
    LockKeyhole,
    RefreshCw,
    Settings2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import Panel from '@/components/comun/Panel.vue';
import IconoServicio from '@/components/servicios/IconoServicio.vue';
import type { ServicioCatalogo } from '@/components/servicios/tipos';
import { situacion } from '@/components/servicios/tipos';
import { Button } from '@/components/ui/button';
import { fechaCorta, pesos } from '@/lib/formato';
import { index, pago as rutaPago, retorno } from '@/routes/cliente/servicios';

type PagoRetorno = {
    folio: string;
    estado: string;
    estado_etiqueta: string;
    importe_centavos: number;
    moneda: string;
    mensaje_error: string | null;
    en_proceso: boolean;
};

const props = defineProps<{
    servicio: ServicioCatalogo;
    pago: PagoRetorno | null;
    historial: {
        id: number;
        estado: string;
        estado_etiqueta: string;
        origen: string;
        inicia_en: string | null;
        vence_en: string | null;
        folio: string | null;
    }[];
    pagos: {
        folio: string;
        estado: string;
        estado_etiqueta: string;
        importe_centavos: number;
        moneda: string;
        proveedor: string;
        fecha: string | null;
    }[];
}>();

defineOptions({
    layout: (props: { servicio: ServicioCatalogo }) => ({
        breadcrumbs: [
            { title: 'Servicios', href: index() },
            { title: props.servicio.nombre },
        ],
    }),
});

const estado = computed(() => situacion(props.servicio));
const confirmar = ref(false);
const form = useForm<{ servicio?: string }>({});

// Solo se envía la intención: el servidor decide servicio, importe y moneda.
const pagar = () =>
    form.post(rutaPago.url(props.servicio.clave), {
        onFinish: () => (confirmar.value = false),
    });

const verificando = ref(false);
const verificar = () => {
    if (!props.pago) {
        return;
    }

    router.get(
        retorno.url({ query: { folio: props.pago.folio } }),
        {},
        {
            preserveScroll: true,
            onStart: () => (verificando.value = true),
            onFinish: () => (verificando.value = false),
        },
    );
};

const resultado = computed(() => {
    const p = props.pago;

    if (!p) {
        return null;
    }

    if (p.estado === 'pagado') {
        return {
            icono: CircleCheck,
            tono: 'border-success/30 bg-success-soft text-success',
            titulo: '¡Pago confirmado!',
            texto: `Openpay confirmó tu pago. ${props.servicio.nombre} ya está activo para tu negocio.`,
        };
    }

    if (p.estado === 'pendiente') {
        return {
            icono: Clock,
            tono: 'border-warning/30 bg-warning-soft text-warning',
            titulo: 'Esperando la confirmación del pago',
            texto: p.en_proceso
                ? 'Aún no recibimos la confirmación de Openpay. Si ya pagaste, puede tardar unos minutos; el servicio se activará automáticamente.'
                : 'El pago no se completó.',
        };
    }

    return {
        icono: CircleAlert,
        tono: 'border-destructive/30 bg-destructive/10 text-destructive',
        titulo: `Pago ${p.estado_etiqueta.toLowerCase()}`,
        texto:
            (p.mensaje_error ? `${p.mensaje_error} ` : '') +
            'No se realizó ningún cargo que active el servicio. Puedes intentarlo de nuevo.',
    };
});

const precio = computed(() =>
    props.servicio.precio_centavos
        ? pesos(props.servicio.precio_centavos)
        : null,
);
</script>

<template>
    <Head :title="servicio.nombre" />

    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6">
        <Link
            :href="index()"
            class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" aria-hidden="true" />
            Todos los servicios
        </Link>

        <div
            v-if="resultado"
            class="flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-start"
            :class="resultado.tono"
            role="status"
        >
            <component
                :is="resultado.icono"
                class="size-6 shrink-0"
                aria-hidden="true"
            />
            <div class="min-w-0 flex-1">
                <p class="font-semibold">{{ resultado.titulo }}</p>
                <p class="mt-0.5 text-sm text-foreground/80">
                    {{ resultado.texto }}
                </p>
                <p class="mt-1 text-xs text-foreground/60">
                    Folio {{ pago?.folio }}
                </p>
            </div>
            <Button
                v-if="pago?.estado === 'pendiente' && pago.en_proceso"
                variant="outline"
                size="sm"
                class="shrink-0 bg-background"
                :disabled="verificando"
                @click="verificar"
            >
                <RefreshCw
                    :class="{ 'animate-spin': verificando }"
                    aria-hidden="true"
                />
                Consultar de nuevo
            </Button>
        </div>

        <section
            class="grid gap-6 rounded-2xl border bg-card p-5 shadow-xs sm:p-7 lg:grid-cols-[1fr_20rem]"
        >
            <div class="min-w-0">
                <div class="flex items-start gap-3">
                    <IconoServicio :clave="servicio.clave" />
                    <div class="min-w-0">
                        <h1
                            class="text-xl font-semibold tracking-tight sm:text-2xl"
                        >
                            {{ servicio.nombre }}
                        </h1>
                        <span
                            class="mt-1.5 inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="estado.tono"
                        >
                            <span
                                class="size-1.5 rounded-full bg-current"
                                aria-hidden="true"
                            />
                            {{ estado.texto }}
                        </span>
                    </div>
                </div>
                <p
                    v-if="servicio.descripcion"
                    class="mt-4 text-muted-foreground"
                >
                    {{ servicio.descripcion }}
                </p>
                <ul
                    v-if="servicio.beneficios.length"
                    class="mt-5 grid gap-2.5 sm:grid-cols-2"
                >
                    <li
                        v-for="b in servicio.beneficios"
                        :key="b"
                        class="flex gap-2 text-sm"
                    >
                        <Check
                            class="mt-0.5 size-4 shrink-0 text-success"
                            aria-hidden="true"
                        />
                        <span>{{ b }}</span>
                    </li>
                </ul>
                <p
                    v-if="servicio.clave === 'pagina_enlaces'"
                    class="mt-5 rounded-lg bg-muted/60 px-3 py-2 text-xs text-muted-foreground"
                >
                    Puedes conectar un dominio propio sin costo adicional en la
                    plataforma. La compra y la renovación del dominio se hacen
                    con tu proveedor y son responsabilidad de su titular.
                </p>
            </div>

            <aside
                class="flex flex-col gap-3 rounded-xl border bg-muted/30 p-4"
            >
                <template v-if="servicio.incluido">
                    <p class="text-sm">
                        Este servicio se incluye con tus productos QR y NFC.
                    </p>
                </template>
                <template v-else-if="servicio.estado.activo">
                    <p class="text-sm font-medium">Servicio activo</p>
                    <p class="text-sm text-muted-foreground">
                        {{
                            servicio.estado.vence_en
                                ? `Vigente hasta el ${fechaCorta(servicio.estado.vence_en)}.`
                                : 'Sin fecha de vencimiento.'
                        }}
                    </p>
                </template>
                <template v-else>
                    <div>
                        <p class="text-xs text-muted-foreground">
                            Precio final
                        </p>
                        <p
                            v-if="precio"
                            class="text-3xl font-semibold tracking-tight tabular-nums"
                        >
                            {{ precio }}
                            <span
                                class="text-sm font-normal text-muted-foreground"
                                >{{ servicio.moneda }}</span
                            >
                        </p>
                        <p v-else class="text-sm">Por confirmar con DASTI</p>
                        <p
                            v-if="precio"
                            class="mt-0.5 text-xs text-muted-foreground"
                        >
                            Pago único{{
                                servicio.impuestos_incluidos
                                    ? ', IVA incluido'
                                    : ', más IVA'
                            }}.
                            {{
                                servicio.vigencia_meses
                                    ? `Vigencia de ${servicio.vigencia_meses} meses.`
                                    : 'Sin mensualidades ni renovación automática.'
                            }}
                        </p>
                    </div>
                </template>

                <Button v-if="servicio.url_modulo" as-child class="w-full">
                    <Link :href="servicio.url_modulo">
                        <Settings2 aria-hidden="true" />
                        Ir a {{ servicio.nombre }}
                    </Link>
                </Button>

                <template v-if="!servicio.incluido && !servicio.estado.activo">
                    <p
                        v-if="servicio.no_disponible"
                        class="rounded-lg border border-warning/30 bg-warning-soft px-3 py-2 text-sm text-warning"
                        role="status"
                    >
                        {{ servicio.no_disponible }}
                    </p>
                    <p
                        v-else-if="!servicio.puede_contratar"
                        class="text-sm text-muted-foreground"
                    >
                        Pide a la persona responsable de tu negocio que lo
                        contrate (necesita el permiso «Contratar»).
                    </p>
                    <DialogoConfirmacion
                        v-else
                        v-model:open="confirmar"
                        titulo="Confirmar contratación"
                        :descripcion="`Vas a pagar ${precio} ${servicio.moneda} por ${servicio.nombre}. Te enviaremos al formulario seguro de Openpay para pagar con tarjeta de crédito o débito.`"
                        confirmar="Ir a pagar"
                        :procesando="form.processing"
                        @confirmar="pagar"
                    >
                        <template #disparador>
                            <Button class="w-full" size="lg">
                                <CreditCard aria-hidden="true" />
                                Contratar por {{ precio }}
                            </Button>
                        </template>
                    </DialogoConfirmacion>
                    <p
                        v-if="form.errors.servicio"
                        class="text-sm text-destructive"
                        role="alert"
                    >
                        {{ form.errors.servicio }}
                    </p>
                    <p class="flex gap-1.5 text-xs text-muted-foreground">
                        <LockKeyhole
                            class="mt-0.5 size-3.5 shrink-0"
                            aria-hidden="true"
                        />
                        Pagas en la página de Openpay. DASTI no recibe ni guarda
                        los datos de tu tarjeta. El servicio se activa cuando
                        Openpay confirma el pago.
                    </p>
                </template>
            </aside>
        </section>

        <div v-if="!servicio.incluido" class="grid gap-6 lg:grid-cols-2">
            <Panel titulo="Historial de activación">
                <EstadoVacio
                    v-if="historial.length === 0"
                    titulo="Sin contrataciones"
                    descripcion="Aquí verás cuándo se activó o cambió el servicio."
                />
                <ul v-else class="divide-y">
                    <li
                        v-for="h in historial"
                        :key="h.id"
                        class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm"
                    >
                        <div class="min-w-0">
                            <p class="font-medium">{{ h.estado_etiqueta }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ h.origen }} · desde
                                {{ fechaCorta(h.inicia_en) }}
                                <template v-if="h.vence_en">
                                    · hasta {{ fechaCorta(h.vence_en) }}
                                </template>
                            </p>
                        </div>
                        <span
                            v-if="h.folio"
                            class="text-xs text-muted-foreground tabular-nums"
                            >{{ h.folio }}</span
                        >
                    </li>
                </ul>
            </Panel>
            <Panel titulo="Pagos">
                <EstadoVacio
                    v-if="pagos.length === 0"
                    titulo="Sin pagos registrados"
                    descripcion="Tus pagos y su estado aparecerán aquí."
                />
                <ul v-else class="divide-y">
                    <li
                        v-for="p in pagos"
                        :key="p.folio"
                        class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm"
                    >
                        <div class="min-w-0">
                            <p class="font-medium tabular-nums">
                                {{ pesos(p.importe_centavos) }} {{ p.moneda }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ p.proveedor }} · {{ fechaCorta(p.fecha) }} ·
                                {{ p.folio }}
                            </p>
                        </div>
                        <span
                            class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium"
                            >{{ p.estado_etiqueta }}</span
                        >
                    </li>
                </ul>
            </Panel>
        </div>
    </div>
</template>
