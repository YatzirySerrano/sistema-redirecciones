<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Camera,
    CircleCheck,
    Gift,
    Nfc,
    ScanLine,
    Search,
    Ticket,
    UserPlus,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import { toast } from 'vue-sonner';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import Panel from '@/components/comun/Panel.vue';
import NavFidelidad from '@/components/fidelidad/NavFidelidad.vue';
import type {
    ConsumidorFicha,
    EstadoServicio,
    ProgresoCampana,
    PuedeFidelidad,
    RecompensaDisponible,
} from '@/components/fidelidad/tipos';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { relativo } from '@/lib/formato';
import { ErrorPeticion, pedir } from '@/lib/peticion';
import { inicio, mostrador } from '@/routes/cliente/fidelidad';
import {
    create as nuevoConsumidor,
    show as verConsumidor,
} from '@/routes/cliente/fidelidad/consumidores';
import { identificar } from '@/routes/cliente/fidelidad/mostrador';
import { canje } from '@/routes/cliente/fidelidad/recompensas';
import { store as registrarVisita } from '@/routes/cliente/fidelidad/visitas';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Fidelidad', href: inicio() },
            { title: 'Mostrador', href: mostrador() },
        ],
    },
});

const props = defineProps<{
    servicio: EstadoServicio;
    puede: PuedeFidelidad;
    campanas: {
        id: number;
        nombre: string;
        meta: number | null;
        recompensa: string | null;
    }[];
}>();

// ---- Identificación ----
const consulta = ref('');
const buscando = ref(false);
const resultados = ref<ConsumidorFicha[]>([]);
const mensaje = ref<string | null>(null);
const credencial = ref<string | null>(null);
const elegido = ref<ConsumidorFicha | null>(null);

const buscar = async (texto = consulta.value) => {
    if (texto.trim().length < 2) {
        mensaje.value = 'Escribe al menos 2 caracteres o escanea la tarjeta.';

        return;
    }

    buscando.value = true;
    mensaje.value = null;

    try {
        const r = await pedir(identificar.url({ query: { q: texto.trim() } }));
        const datos = (await r.json()) as {
            resultados: ConsumidorFicha[];
            mensaje?: string | null;
            credencial?: { codigo: string };
        };
        resultados.value = datos.resultados;
        mensaje.value = datos.mensaje ?? null;
        credencial.value = datos.credencial?.codigo ?? null;
        elegido.value =
            datos.resultados.length === 1 ? datos.resultados[0] : null;
    } catch (e) {
        mensaje.value =
            e instanceof ErrorPeticion
                ? e.message
                : 'No pudimos buscar. Revisa tu conexión.';
    } finally {
        buscando.value = false;
    }
};

const limpiar = () => {
    consulta.value = '';
    resultados.value = [];
    elegido.value = null;
    credencial.value = null;
    mensaje.value = null;
};

// ---- Lectura con cámara (QR) y NFC, si el navegador lo permite ----
const soportaCamara =
    typeof window !== 'undefined' && 'BarcodeDetector' in window;
const soportaNfc = typeof window !== 'undefined' && 'NDEFReader' in window;
const video = ref<HTMLVideoElement | null>(null);
const escaneando = ref(false);
let flujo: MediaStream | null = null;
let detenerEscaneo = false;

const detenerCamara = () => {
    detenerEscaneo = true;
    flujo?.getTracks().forEach((t) => t.stop());
    flujo = null;
    escaneando.value = false;
};

const escanearQr = async () => {
    try {
        flujo = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' },
        });
        escaneando.value = true;
        detenerEscaneo = false;
        await new Promise((r) => setTimeout(r, 50));

        if (!video.value) {
            return;
        }

        video.value.srcObject = flujo;
        await video.value.play();
        // @ts-expect-error BarcodeDetector aún no está en los tipos de TS.
        const detector = new window.BarcodeDetector({ formats: ['qr_code'] });

        while (!detenerEscaneo && video.value) {
            const codigos = (await detector.detect(video.value)) as {
                rawValue: string;
            }[];

            if (codigos.length > 0) {
                consulta.value = codigos[0].rawValue;
                detenerCamara();
                await buscar();

                return;
            }

            await new Promise((r) => setTimeout(r, 250));
        }
    } catch {
        detenerCamara();
        toast.error(
            'No pudimos usar la cámara. Escribe el código de la tarjeta.',
        );
    }
};

const leerNfc = async () => {
    try {
        // @ts-expect-error NDEFReader (Web NFC) solo existe en Chrome Android.
        const lector = new window.NDEFReader();
        await lector.scan();
        toast.info('Acerca la tarjeta NFC al teléfono.');
        lector.onreading = (evento: {
            message: { records: { recordType: string; data: DataView }[] };
        }) => {
            const registro = evento.message.records.find(
                (r) => r.recordType === 'url',
            );

            if (registro) {
                consulta.value = new TextDecoder().decode(registro.data);
                void buscar();
            }
        };
    } catch {
        toast.error('No pudimos leer la tarjeta NFC. Escribe su código.');
    }
};

onBeforeUnmount(detenerCamara);

// ---- Confirmaciones (idempotentes) ----
type Pendiente =
    | { tipo: 'visita'; progreso: ProgresoCampana; uuid: string }
    | { tipo: 'canje'; recompensa: RecompensaDisponible; uuid: string };

const pendiente = ref<Pendiente | null>(null);
const dialogo = computed({
    get: () => pendiente.value !== null,
    set: (v: boolean) => {
        if (!v && !procesando.value) {
            pendiente.value = null;
        }
    },
});
const procesando = ref(false);

// El identificador se genera al abrir la confirmación: un doble clic o un
// reintento envían el mismo y el servidor no duplica la operación.
const confirmarVisita = (progreso: ProgresoCampana) =>
    (pendiente.value = { tipo: 'visita', progreso, uuid: crypto.randomUUID() });
const confirmarCanje = (recompensa: RecompensaDisponible) =>
    (pendiente.value = {
        tipo: 'canje',
        recompensa,
        uuid: crypto.randomUUID(),
    });

const ejecutar = async () => {
    if (!pendiente.value || !elegido.value || procesando.value) {
        return;
    }

    procesando.value = true;
    const p = pendiente.value;

    try {
        const respuesta =
            p.tipo === 'visita'
                ? await pedir(registrarVisita.url(), {
                      metodo: 'POST',
                      datos: {
                          consumidor_id: elegido.value.id,
                          campana_id: p.progreso.campana_id,
                          operacion_uuid: p.uuid,
                          credencial: credencial.value,
                      },
                  })
                : await pedir(canje.url(p.recompensa.id), {
                      metodo: 'POST',
                      datos: { operacion_uuid: p.uuid },
                  });
        const datos = (await respuesta.json()) as {
            mensaje: string;
            repetida: boolean;
            consumidor: ConsumidorFicha;
        };
        elegido.value = datos.consumidor;
        resultados.value = resultados.value.map((c) =>
            c.id === datos.consumidor.id ? datos.consumidor : c,
        );
        toast[datos.repetida ? 'info' : 'success'](datos.mensaje);
        pendiente.value = null;
    } catch (e) {
        // Sin conexión no se simula nada: la operación no se guardó.
        toast.error(
            e instanceof ErrorPeticion
                ? e.message
                : 'No se pudo guardar. Inténtalo nuevamente.',
        );

        if (e instanceof ErrorPeticion && e.estado !== 0) {
            pendiente.value = null;
        }
    } finally {
        procesando.value = false;
    }
};

const sinCampanas = computed(() => props.campanas.length === 0);
</script>

<template>
    <Head title="Mostrador de Fidelidad" />

    <EncabezadoPagina
        titulo="Mostrador"
        descripcion="Identifica al cliente y confirma su visita o entrega su recompensa."
        ayuda="Solo confirma una visita cuando el cliente realmente compró o recibió el servicio. Cada confirmación queda registrada con tu nombre."
        :icono="ScanLine"
    />

    <NavFidelidad :servicio="servicio" />

    <EstadoVacio
        v-if="sinCampanas"
        class="rounded-xl border bg-card"
        :icono="Gift"
        titulo="No hay campañas activas"
        descripcion="Crea una campaña (por ejemplo, «8 visitas = 1 café») para empezar a registrar visitas."
    />

    <div
        v-else
        class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]"
    >
        <Panel titulo="1. Identificar al cliente">
            <form class="space-y-3" @submit.prevent="buscar()">
                <label for="consulta" class="text-sm font-medium"
                    >Código de tarjeta, nombre o teléfono</label
                >
                <div class="flex gap-2">
                    <input
                        id="consulta"
                        v-model="consulta"
                        type="search"
                        inputmode="search"
                        autocomplete="off"
                        autofocus
                        placeholder="Ej. Ana, 5512… o código"
                        class="h-11 min-w-0 flex-1 rounded-lg border border-input bg-background px-3 text-base outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30 sm:text-sm"
                    />
                    <Button
                        type="submit"
                        class="h-11"
                        :disabled="buscando"
                        aria-label="Buscar"
                    >
                        <Spinner v-if="buscando" />
                        <Search v-else />
                    </Button>
                </div>
                <div
                    v-if="soportaCamara || soportaNfc"
                    class="flex flex-wrap gap-2"
                >
                    <Button
                        v-if="soportaCamara"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="escaneando ? detenerCamara() : escanearQr()"
                    >
                        <Camera />
                        {{ escaneando ? 'Detener cámara' : 'Escanear QR' }}
                    </Button>
                    <Button
                        v-if="soportaNfc"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="leerNfc"
                    >
                        <Nfc /> Leer NFC
                    </Button>
                </div>
                <video
                    v-show="escaneando"
                    ref="video"
                    class="aspect-square w-full rounded-lg bg-black object-cover"
                    muted
                    playsinline
                />
                <p
                    v-if="mensaje"
                    class="text-sm text-muted-foreground"
                    role="status"
                >
                    {{ mensaje }}
                </p>
            </form>

            <ul
                v-if="resultados.length > 1"
                class="mt-4 divide-y rounded-lg border"
            >
                <li v-for="c in resultados" :key="c.id">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left text-sm hover:bg-muted/50 aria-pressed:bg-primary/10"
                        :aria-pressed="elegido?.id === c.id"
                        @click="elegido = c"
                    >
                        <span class="min-w-0">
                            <span class="block truncate font-medium">{{
                                c.nombre
                            }}</span>
                            <span
                                class="block truncate text-xs text-muted-foreground"
                                >{{
                                    c.telefono ?? c.email ?? 'Sin contacto'
                                }}</span
                            >
                        </span>
                        <span
                            v-if="c.recompensas.length"
                            class="shrink-0 rounded-full bg-accent px-2 py-0.5 text-xs font-medium text-accent-foreground"
                            >{{ c.recompensas.length }} 🎁</span
                        >
                    </button>
                </li>
            </ul>

            <Button
                v-if="puede.consumidores"
                variant="ghost"
                size="sm"
                class="mt-3"
                as-child
            >
                <Link :href="nuevoConsumidor()"
                    ><UserPlus /> Registrar cliente nuevo</Link
                >
            </Button>
        </Panel>

        <Panel
            :titulo="elegido ? `2. ${elegido.nombre}` : '2. Confirmar'"
            :descripcion="
                elegido
                    ? `${elegido.alias ? elegido.alias + ' · ' : ''}${elegido.telefono ?? elegido.email ?? ''}`
                    : 'Selecciona un cliente para ver su progreso.'
            "
        >
            <template v-if="elegido">
                <div
                    v-if="elegido.recompensas.length"
                    class="mb-5 rounded-xl border border-primary/25 bg-accent/60 p-4"
                >
                    <p
                        class="flex items-center gap-2 text-sm font-semibold text-accent-foreground"
                    >
                        <Gift class="size-4" /> Recompensas disponibles
                    </p>
                    <ul class="mt-3 space-y-2">
                        <li
                            v-for="r in elegido.recompensas"
                            :key="r.id"
                            class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-card px-3 py-2"
                        >
                            <span class="text-sm">
                                {{ r.descripcion }}
                                <span
                                    v-if="r.vence_en"
                                    class="block text-xs text-muted-foreground"
                                    >Vence {{ relativo(r.vence_en) }}</span
                                >
                            </span>
                            <Button
                                v-if="puede.canjear"
                                size="sm"
                                @click="confirmarCanje(r)"
                                ><CircleCheck /> Entregar</Button
                            >
                        </li>
                    </ul>
                </div>

                <ul class="space-y-3">
                    <li
                        v-for="p in elegido.progreso"
                        :key="p.campana_id"
                        class="rounded-xl border p-4"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <p class="font-medium">{{ p.campana }}</p>
                                <p class="text-xs text-muted-foreground">
                                    Recompensa: {{ p.recompensa }}
                                    <template v-if="p.ultima_visita_en">
                                        · última visita
                                        {{
                                            relativo(p.ultima_visita_en)
                                        }}</template
                                    >
                                </p>
                            </div>
                            <span class="text-lg font-semibold tabular-nums"
                                >{{ p.visitas
                                }}<span class="text-sm text-muted-foreground"
                                    >/{{ p.meta }}</span
                                ></span
                            >
                        </div>
                        <div class="mt-3 flex gap-1" aria-hidden="true">
                            <span
                                v-for="n in p.meta"
                                :key="n"
                                class="h-2 flex-1 rounded-full"
                                :class="
                                    n <= p.visitas ? 'bg-primary' : 'bg-muted'
                                "
                            />
                        </div>
                        <Button
                            v-if="puede.visitas && !p.completada"
                            class="mt-4 h-11 w-full sm:w-auto"
                            @click="confirmarVisita(p)"
                        >
                            <Ticket /> Registrar visita
                        </Button>
                        <p
                            v-else-if="p.completada"
                            class="mt-3 text-sm text-muted-foreground"
                        >
                            Completó esta campaña.
                        </p>
                    </li>
                </ul>

                <div class="mt-4 flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="verConsumidor(elegido.id)"
                            >Ver ficha completa</Link
                        >
                    </Button>
                    <Button variant="ghost" size="sm" @click="limpiar"
                        >Atender a otro cliente</Button
                    >
                </div>
            </template>
            <EstadoVacio
                v-else
                :icono="ScanLine"
                titulo="Ningún cliente seleccionado"
                descripcion="Escanea su tarjeta o búscalo por nombre o teléfono."
            />
        </Panel>
    </div>

    <DialogoConfirmacion
        v-model:open="dialogo"
        :titulo="
            pendiente?.tipo === 'canje'
                ? '¿Entregar la recompensa?'
                : '¿Confirmar la visita?'
        "
        :descripcion="
            pendiente?.tipo === 'canje'
                ? `Entregarás «${pendiente.recompensa.descripcion}» a ${elegido?.nombre}. Una recompensa solo se puede canjear una vez.`
                : pendiente
                  ? `Sumarás 1 visita a ${elegido?.nombre} en «${pendiente.progreso.campana}». Confírmala solo si realizó la compra o recibió el servicio.`
                  : ''
        "
        :confirmar="
            pendiente?.tipo === 'canje'
                ? 'Entregar recompensa'
                : 'Confirmar visita'
        "
        :procesando="procesando"
        @confirmar="ejecutar"
    />
</template>
