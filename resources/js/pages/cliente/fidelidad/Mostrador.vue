<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Camera,
    CameraOff,
    CircleCheck,
    Gift,
    Nfc,
    ScanLine,
    Search,
    Ticket,
    UserPlus,
    X,
} from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import EstadoVacio from '@/components/comun/EstadoVacio.vue';
import Panel from '@/components/comun/Panel.vue';
import { fragmentos } from '@/components/fidelidad/busqueda';
import NavFidelidad from '@/components/fidelidad/NavFidelidad.vue';
import type {
    ConsumidorFicha,
    EstadoServicio,
    ProgresoCampana,
    PuedeFidelidad,
    RecompensaDisponible,
    ResultadoBusqueda,
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
import {
    consumidor as fichaConsumidor,
    identificar,
} from '@/routes/cliente/fidelidad/mostrador';
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
    busqueda: { espera_ms: number; min_caracteres: number };
}>();

/*
 * Búsqueda en tiempo real. Política de identidad:
 * - Solo un CÓDIGO DE TARJETA exacto (escrito, escaneado o leído por NFC) se
 *   preselecciona: es una credencial única del negocio.
 * - Nombre, alias o teléfono NUNCA se eligen solos, aunque haya un único
 *   resultado: el personal confirma con el cliente y elige.
 * - Buscar o seleccionar nunca registra visitas; eso solo ocurre al
 *   confirmar en el diálogo (operación idempotente con operacion_uuid).
 */
const consulta = ref('');
const entrada = ref<HTMLInputElement | null>(null);
const estadoBusqueda = ref<'inactivo' | 'buscando' | 'listo' | 'error'>(
    'inactivo',
);
const resultados = ref<ResultadoBusqueda[]>([]);
const hayMas = ref(false);
const pagina = ref(1);
const mensaje = ref<string | null>(null);
const credencial = ref<string | null>(null);
const elegido = ref<ConsumidorFicha | null>(null);
const cargandoFicha = ref(false);
const activo = ref(-1);
const abierta = ref(false);
const buscada = ref('');

let espera: ReturnType<typeof setTimeout> | null = null;
let controlador: AbortController | null = null;
let secuencia = 0;
let secuenciaFicha = 0;
let omitir: string | null = null;

const listaVisible = computed(
    () => abierta.value && resultados.value.length > 0,
);
const idOpcion = (r: ResultadoBusqueda) => `consumidor-opcion-${r.id}`;
const ETIQUETAS: Record<ResultadoBusqueda['coincidencia'], string | null> = {
    codigo: 'Tarjeta',
    telefono_exacto: 'Teléfono exacto',
    telefono: 'Teléfono',
    correo: 'Correo',
    nombre: null,
};

const cancelarPendiente = () => {
    if (espera) {
        clearTimeout(espera);
        espera = null;
    }

    controlador?.abort();
    controlador = null;
};

const buscar = async (texto: string, nuevaPagina = 1) => {
    const limpio = texto.trim();
    cancelarPendiente();

    if (limpio.length < props.busqueda.min_caracteres) {
        secuencia++;
        resultados.value = [];
        hayMas.value = false;
        estadoBusqueda.value = 'inactivo';
        mensaje.value =
            limpio.length > 0
                ? `Escribe al menos ${props.busqueda.min_caracteres} caracteres.`
                : null;

        return;
    }

    const id = ++secuencia;
    controlador = new AbortController();
    estadoBusqueda.value = 'buscando';

    try {
        const r = await pedir(
            identificar.url({ query: { q: limpio, pagina: nuevaPagina } }),
            { senal: controlador.signal },
        );
        const datos = (await r.json()) as {
            resultados: ResultadoBusqueda[];
            hay_mas: boolean;
            pagina: number;
            preseleccion: number | null;
            credencial: { codigo: string } | null;
            mensaje: string | null;
        };

        // Respuesta vieja (el texto cambió mientras viajaba): se descarta.
        if (id !== secuencia) {
            return;
        }

        resultados.value =
            nuevaPagina > 1
                ? [...resultados.value, ...datos.resultados]
                : datos.resultados;
        hayMas.value = datos.hay_mas;
        pagina.value = datos.pagina;
        mensaje.value = datos.mensaje;
        buscada.value = limpio;
        credencial.value = datos.credencial?.codigo ?? null;
        estadoBusqueda.value = 'listo';
        abierta.value = true;
        activo.value = nuevaPagina > 1 ? activo.value : -1;

        const pre = datos.resultados.find((x) => x.id === datos.preseleccion);

        if (pre) {
            void seleccionar(pre);
        }
    } catch (e) {
        if (e instanceof DOMException && e.name === 'AbortError') {
            return;
        }

        if (id !== secuencia) {
            return;
        }

        estadoBusqueda.value = 'error';
        resultados.value = [];
        mensaje.value =
            e instanceof ErrorPeticion
                ? e.estado === 429
                    ? 'Muchas búsquedas seguidas. Espera unos segundos y vuelve a escribir.'
                    : e.message
                : 'No pudimos buscar. Revisa tu conexión.';
    }
};

/** Búsqueda inmediata (Enter, QR o NFC), sin esperar la pausa de tecleo. */
const buscarAhora = (texto = consulta.value) => {
    omitir = texto;
    void buscar(texto);
};

watch(consulta, (texto) => {
    if (omitir === texto) {
        omitir = null;

        return;
    }

    omitir = null;

    if (espera) {
        clearTimeout(espera);
    }

    if (texto.trim().length < props.busqueda.min_caracteres) {
        void buscar(texto);

        return;
    }

    espera = setTimeout(() => void buscar(texto), props.busqueda.espera_ms);
});

const seleccionar = async (r: ResultadoBusqueda) => {
    abierta.value = false;
    activo.value = resultados.value.indexOf(r);

    if (r.coincidencia !== 'codigo') {
        credencial.value = null;
    }

    const id = ++secuenciaFicha;
    cargandoFicha.value = true;

    try {
        const respuesta = await pedir(fichaConsumidor.url(r.id));
        const datos = (await respuesta.json()) as {
            consumidor: ConsumidorFicha;
        };

        if (id === secuenciaFicha) {
            elegido.value = datos.consumidor;
        }
    } catch (e) {
        if (id === secuenciaFicha) {
            toast.error(
                e instanceof ErrorPeticion
                    ? e.message
                    : 'No pudimos abrir la ficha. Inténtalo nuevamente.',
            );
        }
    } finally {
        if (id === secuenciaFicha) {
            cargandoFicha.value = false;
        }
    }
};

const teclas = (e: KeyboardEvent) => {
    const total = resultados.value.length;

    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        if (total === 0) {
            return;
        }

        e.preventDefault();
        abierta.value = true;
        activo.value =
            e.key === 'ArrowDown'
                ? (activo.value + 1) % total
                : (activo.value - 1 + total) % total;
        void nextTick(() =>
            document
                .getElementById(idOpcion(resultados.value[activo.value]))
                ?.scrollIntoView({ block: 'nearest' }),
        );
    } else if (e.key === 'Enter') {
        e.preventDefault();

        if (listaVisible.value && activo.value >= 0) {
            void seleccionar(resultados.value[activo.value]);
        } else if (consulta.value.trim() !== buscada.value) {
            buscarAhora();
        } else if (total > 0) {
            abierta.value = true;
            activo.value = 0;
        }
    } else if (e.key === 'Escape') {
        if (listaVisible.value) {
            e.preventDefault();
            abierta.value = false;
        } else if (consulta.value) {
            e.preventDefault();
            consulta.value = '';
        }
    }
};

const limpiar = () => {
    cancelarPendiente();
    secuencia++;
    secuenciaFicha++;
    consulta.value = '';
    resultados.value = [];
    hayMas.value = false;
    elegido.value = null;
    credencial.value = null;
    mensaje.value = null;
    estadoBusqueda.value = 'inactivo';
    cargandoFicha.value = false;
    buscada.value = '';
    void nextTick(() => entrada.value?.focus());
};

// ---- Lectura con cámara (QR) y NFC ----
const soportaQr = typeof window !== 'undefined' && 'BarcodeDetector' in window;
const soportaNfc = typeof window !== 'undefined' && 'NDEFReader' in window;
const video = ref<HTMLVideoElement | null>(null);
const camara = ref<'apagada' | 'iniciando' | 'activa'>('apagada');
const avisoCamara = ref<string | null>(null);
let flujo: MediaStream | null = null;
let turnoCamara = 0;

const detenerCamara = () => {
    turnoCamara++;
    flujo?.getTracks().forEach((t) => t.stop());
    flujo = null;

    if (video.value) {
        video.value.srcObject = null;
    }

    camara.value = 'apagada';
};

const mensajeCamara = (error: unknown): string => {
    const nombre = error instanceof DOMException ? error.name : '';

    return (
        {
            NotAllowedError:
                'Permiso de cámara denegado. Actívalo en la configuración del navegador para este sitio o escribe el código.',
            SecurityError:
                'Permiso de cámara denegado. Actívalo en la configuración del navegador para este sitio o escribe el código.',
            NotFoundError: 'No encontramos una cámara en este dispositivo.',
            OverconstrainedError:
                'No encontramos una cámara en este dispositivo.',
            NotReadableError:
                'La cámara está en uso por otra aplicación. Ciérrala e inténtalo de nuevo.',
        }[nombre] ??
        'No pudimos usar la cámara. Escribe el código de la tarjeta.'
    );
};

const escanearQr = async () => {
    avisoCamara.value = null;

    if (!soportaQr) {
        avisoCamara.value =
            'Este navegador no puede leer códigos QR con la cámara. Usa Chrome o Edge en Android, un lector de códigos o escribe el código de la tarjeta.';

        return;
    }

    if (!navigator.mediaDevices?.getUserMedia) {
        avisoCamara.value =
            'La cámara no está disponible en esta conexión. Abre el panel con https:// o escribe el código.';

        return;
    }

    const turno = ++turnoCamara;
    camara.value = 'iniciando';

    try {
        const nuevo = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' },
            audio: false,
        });

        // Se canceló (o se salió de la página) mientras se pedía permiso.
        if (turno !== turnoCamara) {
            nuevo.getTracks().forEach((t) => t.stop());

            return;
        }

        flujo = nuevo;
        camara.value = 'activa';
        await nextTick();

        if (!video.value) {
            detenerCamara();

            return;
        }

        video.value.srcObject = flujo;
        await video.value.play();
        // @ts-expect-error BarcodeDetector aún no está en los tipos de TS.
        const detector = new window.BarcodeDetector({ formats: ['qr_code'] });

        while (turno === turnoCamara && video.value) {
            const codigos = (await detector.detect(video.value)) as {
                rawValue: string;
            }[];

            if (turno !== turnoCamara) {
                return;
            }

            if (codigos.length > 0 && codigos[0].rawValue) {
                detenerCamara();
                consulta.value = codigos[0].rawValue.slice(0, 255);
                buscarAhora(consulta.value);

                return;
            }

            await new Promise((r) => setTimeout(r, 200));
        }
    } catch (e) {
        if (turno === turnoCamara) {
            detenerCamara();
            avisoCamara.value = mensajeCamara(e);
        }
    }
};

const nfc = ref(false);
let controladorNfc: AbortController | null = null;

const detenerNfc = () => {
    controladorNfc?.abort();
    controladorNfc = null;
    nfc.value = false;
};

const leerNfc = async () => {
    if (nfc.value) {
        detenerNfc();

        return;
    }

    avisoCamara.value = null;
    controladorNfc = new AbortController();

    try {
        // @ts-expect-error NDEFReader (Web NFC) solo existe en Chrome Android.
        const lector = new window.NDEFReader();
        await lector.scan({ signal: controladorNfc.signal });
        nfc.value = true;
        toast.info('Acerca la tarjeta NFC a la parte trasera del teléfono.');
        lector.onreading = (evento: {
            message: { records: { recordType: string; data: DataView }[] };
        }) => {
            const registro =
                evento.message.records.find((r) => r.recordType === 'url') ??
                evento.message.records.find((r) => r.recordType === 'text');

            if (registro) {
                detenerNfc();
                consulta.value = new TextDecoder()
                    .decode(registro.data)
                    .slice(0, 255);
                buscarAhora(consulta.value);
            }
        };
    } catch (e) {
        detenerNfc();

        if (e instanceof DOMException && e.name === 'AbortError') {
            return;
        }

        avisoCamara.value =
            e instanceof DOMException && e.name === 'NotAllowedError'
                ? 'Permiso de NFC denegado. Actívalo en la configuración del navegador.'
                : e instanceof DOMException && e.name === 'NotSupportedError'
                  ? 'Este teléfono no tiene NFC o está desactivado.'
                  : 'No pudimos leer la tarjeta NFC. Escribe su código.';
    }
};

onBeforeUnmount(() => {
    cancelarPendiente();
    detenerCamara();
    detenerNfc();
});

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

    <NavFidelidad :servicio="servicio" />

    <EncabezadoPagina
        titulo="Mostrador"
        descripcion="Identifica al cliente y confirma su visita o entrega su recompensa."
        ayuda="Solo confirma una visita cuando el cliente realmente compró o recibió el servicio. Cada confirmación queda registrada con tu nombre."
        :icono="ScanLine"
    />

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
            <form class="space-y-3" role="search" @submit.prevent>
                <label for="consulta" class="text-sm font-medium"
                    >Código de tarjeta, nombre o teléfono</label
                >
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <input
                        id="consulta"
                        ref="entrada"
                        v-model="consulta"
                        type="text"
                        role="combobox"
                        inputmode="search"
                        enterkeyhint="search"
                        autocomplete="off"
                        autocapitalize="off"
                        spellcheck="false"
                        maxlength="255"
                        autofocus
                        aria-autocomplete="list"
                        aria-controls="consumidores-resultados"
                        :aria-expanded="listaVisible"
                        :aria-activedescendant="
                            listaVisible && activo >= 0
                                ? idOpcion(resultados[activo])
                                : undefined
                        "
                        aria-describedby="consulta-estado"
                        placeholder="Ej. Ana, 5512… o escanea la tarjeta"
                        class="h-11 w-full rounded-lg border border-input bg-background pr-10 pl-9 text-base outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30 sm:text-sm"
                        @keydown="teclas"
                        @focus="abierta = resultados.length > 0"
                    />
                    <span
                        class="absolute top-1/2 right-2 flex -translate-y-1/2 items-center"
                    >
                        <Spinner
                            v-if="estadoBusqueda === 'buscando'"
                            class="mr-1 size-4"
                        />
                        <button
                            v-else-if="consulta"
                            type="button"
                            class="flex size-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                            aria-label="Borrar búsqueda"
                            @click="limpiar"
                        >
                            <X class="size-4" />
                        </button>
                    </span>

                    <ul
                        v-show="listaVisible"
                        id="consumidores-resultados"
                        role="listbox"
                        aria-label="Consumidores encontrados"
                        class="absolute inset-x-0 top-full z-30 mt-1 max-h-80 overflow-y-auto rounded-lg border bg-popover p-1 text-popover-foreground shadow-lg"
                    >
                        <li
                            v-for="(r, i) in resultados"
                            :id="idOpcion(r)"
                            :key="r.id"
                            role="option"
                            :aria-selected="i === activo"
                            class="flex cursor-pointer items-center justify-between gap-3 rounded-md px-3 py-2.5 text-sm aria-selected:bg-primary/10"
                            :class="
                                elegido?.id === r.id
                                    ? 'ring-1 ring-primary/40'
                                    : ''
                            "
                            @mousedown.prevent
                            @mouseenter="activo = i"
                            @click="seleccionar(r)"
                        >
                            <span class="min-w-0">
                                <span class="block truncate font-medium">
                                    <template
                                        v-for="(f, j) in fragmentos(
                                            r.nombre,
                                            buscada,
                                        )"
                                        :key="j"
                                        ><mark
                                            v-if="f.marca"
                                            class="rounded-sm bg-primary/15 px-0.5 text-inherit"
                                            >{{ f.texto }}</mark
                                        ><template v-else>{{
                                            f.texto
                                        }}</template></template
                                    >
                                    <span
                                        v-if="r.alias"
                                        class="font-normal text-muted-foreground"
                                    >
                                        («<template
                                            v-for="(f, j) in fragmentos(
                                                r.alias,
                                                buscada,
                                            )"
                                            :key="j"
                                            ><mark
                                                v-if="f.marca"
                                                class="rounded-sm bg-primary/15 px-0.5 text-inherit"
                                                >{{ f.texto }}</mark
                                            ><template v-else>{{
                                                f.texto
                                            }}</template></template
                                        >»)</span
                                    >
                                </span>
                                <span
                                    class="block truncate text-xs text-muted-foreground"
                                >
                                    {{ r.telefono ?? 'Sin teléfono' }}
                                    <template v-if="r.campanas.length">
                                        · {{ r.campanas.join(', ') }}</template
                                    >
                                </span>
                            </span>
                            <span class="flex shrink-0 items-center gap-1.5">
                                <span
                                    v-if="ETIQUETAS[r.coincidencia]"
                                    class="rounded-full border px-2 py-0.5 text-[0.7rem] text-muted-foreground"
                                    >{{ ETIQUETAS[r.coincidencia] }}</span
                                >
                                <span
                                    v-if="r.recompensas"
                                    class="inline-flex items-center gap-1 rounded-full bg-accent px-2 py-0.5 text-xs font-medium text-accent-foreground"
                                    :aria-label="`${r.recompensas} recompensas disponibles`"
                                    ><Gift
                                        class="size-3"
                                        aria-hidden="true"
                                    />{{ r.recompensas }}</span
                                >
                            </span>
                        </li>
                        <li v-if="hayMas" role="presentation" class="p-1">
                            <button
                                type="button"
                                class="w-full rounded-md py-2 text-center text-xs font-medium text-primary hover:bg-muted"
                                @mousedown.prevent
                                @click="buscar(buscada, pagina + 1)"
                            >
                                Ver más resultados
                            </button>
                        </li>
                    </ul>
                </div>

                <p
                    id="consulta-estado"
                    class="min-h-5 text-sm"
                    :class="
                        estadoBusqueda === 'error'
                            ? 'text-destructive'
                            : 'text-muted-foreground'
                    "
                    role="status"
                    aria-live="polite"
                >
                    <template v-if="estadoBusqueda === 'buscando'"
                        >Buscando…</template
                    >
                    <template v-else-if="mensaje">{{ mensaje }}</template>
                    <template
                        v-else-if="
                            estadoBusqueda === 'listo' && resultados.length > 1
                        "
                        >{{ resultados.length
                        }}{{ hayMas ? '+' : '' }} coincidencias. Confirma con el
                        cliente su teléfono o alias antes de elegir.</template
                    >
                    <template
                        v-else-if="
                            estadoBusqueda === 'listo' &&
                            resultados.length === 1 &&
                            !elegido
                        "
                        >1 coincidencia. Confírmala con el cliente y
                        elígela.</template
                    >
                </p>

                <div class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :aria-pressed="camara !== 'apagada'"
                        @click="
                            camara === 'apagada'
                                ? escanearQr()
                                : detenerCamara()
                        "
                    >
                        <CameraOff v-if="camara !== 'apagada'" />
                        <Camera v-else />
                        {{
                            camara === 'apagada'
                                ? 'Escanear QR'
                                : camara === 'iniciando'
                                  ? 'Cancelar'
                                  : 'Cerrar cámara'
                        }}
                    </Button>
                    <Button
                        v-if="soportaNfc"
                        type="button"
                        variant="outline"
                        size="sm"
                        :aria-pressed="nfc"
                        @click="leerNfc"
                    >
                        <Nfc /> {{ nfc ? 'Detener NFC' : 'Leer NFC' }}
                    </Button>
                </div>
                <p
                    v-if="avisoCamara"
                    class="rounded-lg border border-warning/30 bg-warning-soft px-3 py-2 text-xs text-warning"
                    role="alert"
                >
                    {{ avisoCamara }}
                </p>
                <div v-show="camara !== 'apagada'" class="relative">
                    <video
                        ref="video"
                        class="aspect-square w-full rounded-lg bg-black object-cover"
                        muted
                        playsinline
                        aria-label="Cámara para escanear el código QR"
                    />
                    <p
                        class="absolute inset-x-0 bottom-2 text-center text-xs text-white/90"
                    >
                        {{
                            camara === 'iniciando'
                                ? 'Abriendo la cámara…'
                                : 'Apunta al código QR de la tarjeta'
                        }}
                    </p>
                </div>
            </form>

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
            <div
                v-if="cargandoFicha && !elegido"
                class="flex items-center gap-2 py-10 text-sm text-muted-foreground"
                role="status"
            >
                <Spinner /> Abriendo ficha…
            </div>
            <template v-else-if="elegido">
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
