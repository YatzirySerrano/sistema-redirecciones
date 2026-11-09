<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { refDebounced } from '@vueuse/core';
import { CircleAlert, Download, ImageOff, RotateCcw, Save } from '@lucide/vue';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import SelectNativo from '@/components/comun/SelectNativo.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { ErrorPeticion, pedir } from '@/lib/peticion';
import { show as qrShow, update as qrUpdate } from '@/routes/productos/qr';
import type { OpcionesQr, Producto } from '@/types';

/**
 * Diseño, vista previa y descarga del QR de un producto. El QR siempre
 * contiene la URL permanente; no lleva el nombre ni el logotipo de DASTI.
 *
 * La vista previa y las descargas se piden con fetch para poder mostrar el
 * motivo real de un error (contraste, logotipo dañado, permisos, sesión
 * expirada o límite de solicitudes) en lugar de una imagen rota.
 */
const props = defineProps<{
    producto: Producto;
    opciones: OpcionesQr;
}>();

const diseno = reactive<OpcionesQr>({ ...props.opciones });
const tamano = ref('1024');
const cargando = ref(true);
const errorVista = ref<string | null>(null);
const imagenVista = ref<string | null>(null);
const guardando = ref(false);
const descargando = ref<'png' | 'svg' | null>(null);
const errorDescarga = ref<string | null>(null);
const erroresGuardado = ref<Record<string, string>>({});

const tieneLogo = computed(() => props.producto.negocio?.tiene_logo ?? false);
const puedeGuardar = computed(() => props.producto.permisos.disenar_qr);

const MARGENES = [
    { value: 'ninguno', label: 'Sin margen' },
    { value: 'reducido', label: 'Reducido' },
    { value: 'estandar', label: 'Estándar (recomendado)' },
    { value: 'amplio', label: 'Amplio' },
];
const TAMANOS = [
    { value: '256', label: '256 px' },
    { value: '512', label: '512 px' },
    { value: '1024', label: '1024 px (impresión pequeña)' },
    { value: '2048', label: '2048 px (impresión grande)' },
];
// Forma de los ojos y de los módulos. Cada opción lleva un icono SVG que
// dibuja la forma con el mismo radio que usa el servidor.
const ESTILOS_OJOS: {
    value: OpcionesQr['estilo_ojos'];
    label: string;
    marco: string;
}[] = [
    {
        value: 'redondeado',
        label: 'Redondeados',
        marco: 'M8,2H16A6,6 0 0 1 22,8V16A6,6 0 0 1 16,22H8A6,6 0 0 1 2,16V8A6,6 0 0 1 8,2Z',
    },
    {
        value: 'hoja',
        label: 'Hoja',
        marco: 'M11,2H20.5A1.5,1.5 0 0 1 22,3.5V13A9,9 0 0 1 13,22H3.5A1.5,1.5 0 0 1 2,20.5V11A9,9 0 0 1 11,2Z',
    },
    { value: 'cuadrado', label: 'Cuadrados', marco: 'M2,2H22V22H2Z' },
];
const ESTILOS_MODULOS: {
    value: OpcionesQr['estilo_modulos'];
    label: string;
}[] = [
    { value: 'redondeado', label: 'Fluidos' },
    { value: 'puntos', label: 'Puntos' },
    { value: 'cuadrado', label: 'Cuadrados' },
];
// Patrón de muestra (5×5) para el icono de los módulos.
const MUESTRA_MODULOS = [
    [0, 0],
    [1, 0],
    [3, 0],
    [0, 1],
    [2, 1],
    [3, 1],
    [4, 1],
    [1, 2],
    [2, 2],
    [4, 2],
    [0, 3],
    [1, 3],
    [3, 3],
    [0, 4],
    [2, 4],
    [3, 4],
    [4, 4],
];

const PREDEFINIDOS = [
    '#000000',
    '#1F2937',
    '#4C1D95',
    '#1E3A8A',
    '#064E3B',
    '#7F1D1D',
];

// Misma fórmula de contraste que el servidor (WCAG).
const luminancia = (hex: string) => {
    const [r, g, b] = [1, 3, 5]
        .map((i) => parseInt(hex.slice(i, i + 2), 16) / 255)
        .map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

const legible = computed(() => {
    const lc = luminancia(diseno.color);

    if (diseno.fondo_transparente) {
        return lc <= 0.2;
    }

    const lf = luminancia(diseno.fondo);

    return (
        lc < lf && (Math.max(lc, lf) + 0.05) / (Math.min(lc, lf) + 0.05) >= 4
    );
});

const consulta = (extra: Record<string, string | number> = {}) => ({
    color: diseno.color,
    fondo: diseno.fondo,
    fondo_transparente: diseno.fondo_transparente ? 1 : 0,
    margen: diseno.margen,
    incluir_logo: diseno.incluir_logo && tieneLogo.value ? 1 : 0,
    estilo_ojos: diseno.estilo_ojos,
    estilo_modulos: diseno.estilo_modulos,
    ...extra,
});

const vistaPrevia = computed(() =>
    qrShow.url(props.producto.id, {
        query: consulta({ formato: 'svg', tamano: 512 }),
    }),
);
const vistaPreviaEstable = refDebounced(vistaPrevia, 250);
let peticionVista: AbortController | null = null;

const reemplazarImagen = (nueva: string | null) => {
    if (imagenVista.value) {
        URL.revokeObjectURL(imagenVista.value);
    }

    imagenVista.value = nueva;
};

const cargarVistaPrevia = async (url: string) => {
    peticionVista?.abort();

    if (!legible.value) {
        // No se pide al servidor un diseño que rechazará.
        cargando.value = false;
        errorVista.value =
            'Ajusta el contraste para ver la vista previa del código.';

        return;
    }

    peticionVista = new AbortController();
    cargando.value = true;
    errorVista.value = null;

    try {
        const respuesta = await pedir(url, { senal: peticionVista.signal });
        reemplazarImagen(URL.createObjectURL(await respuesta.blob()));
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return;
        }

        reemplazarImagen(null);
        errorVista.value =
            error instanceof ErrorPeticion
                ? error.message
                : 'No se pudo generar la vista previa.';
    } finally {
        cargando.value = false;
    }
};

watch(vistaPreviaEstable, (url) => void cargarVistaPrevia(url), {
    immediate: true,
});
onBeforeUnmount(() => {
    peticionVista?.abort();
    reemplazarImagen(null);
});

const nombreArchivo = (respuesta: Response, formato: string) =>
    /filename="([^"]+)"/.exec(
        respuesta.headers.get('Content-Disposition') ?? '',
    )?.[1] ?? `qr-${props.producto.codigo}.${formato}`;

const descargar = async (formato: 'png' | 'svg') => {
    if (!legible.value || descargando.value) {
        return;
    }

    descargando.value = formato;
    errorDescarga.value = null;

    try {
        const respuesta = await pedir(
            qrShow.url(props.producto.id, {
                query: consulta({
                    formato,
                    tamano: Number(tamano.value),
                    descargar: 1,
                }),
            }),
        );
        const enlace = document.createElement('a');
        enlace.href = URL.createObjectURL(await respuesta.blob());
        enlace.download = nombreArchivo(respuesta, formato);
        document.body.appendChild(enlace);
        enlace.click();
        enlace.remove();
        setTimeout(() => URL.revokeObjectURL(enlace.href), 1000);
    } catch (error) {
        errorDescarga.value =
            error instanceof ErrorPeticion
                ? error.message
                : 'No se pudo descargar el archivo.';
        toast.error(errorDescarga.value);
    } finally {
        descargando.value = null;
    }
};

const modificado = computed(() =>
    (Object.keys(props.opciones) as (keyof OpcionesQr)[]).some(
        (k) => diseno[k] !== props.opciones[k],
    ),
);

const restablecer = () =>
    Object.assign(diseno, {
        color: '#000000',
        fondo: '#FFFFFF',
        fondo_transparente: false,
        margen: 'estandar',
        incluir_logo: false,
        estilo_ojos: 'redondeado',
        estilo_modulos: 'redondeado',
    });

const guardar = () => {
    router.put(qrUpdate.url(props.producto.id), consulta(), {
        preserveScroll: true,
        onStart: () => {
            guardando.value = true;
            erroresGuardado.value = {};
        },
        onError: (errores) => {
            erroresGuardado.value = errores;
            toast.error('No se guardó el diseño. Revisa el motivo indicado.');
        },
        onFinish: () => (guardando.value = false),
    });
};
</script>

<template>
    <div class="@container">
        <div
            class="grid grid-cols-1 gap-6 @2xl:grid-cols-[minmax(0,240px)_minmax(0,1fr)]"
        >
            <div class="mx-auto w-full max-w-64 space-y-3">
                <div
                    class="relative flex aspect-square items-center justify-center overflow-hidden rounded-xl border bg-[repeating-conic-gradient(var(--muted)_0%_25%,transparent_0%_50%)] bg-[length:16px_16px]"
                >
                    <img
                        v-if="imagenVista && !errorVista"
                        :src="imagenVista"
                        :alt="`Vista previa del QR de ${producto.nombre}`"
                        class="size-full object-contain"
                    />
                    <div
                        v-if="cargando && !errorVista"
                        class="absolute inset-0 flex items-center justify-center bg-card/60"
                    >
                        <Spinner class="size-6 text-primary" />
                    </div>
                    <div
                        v-if="errorVista"
                        class="flex flex-col items-center gap-2 p-6 text-center text-sm text-muted-foreground"
                        role="status"
                    >
                        <ImageOff class="size-6" aria-hidden="true" />
                        {{ errorVista }}
                    </div>
                </div>
                <p class="text-center text-xs break-all text-muted-foreground">
                    {{ producto.url_permanente }}
                </p>
            </div>

            <div class="@container min-w-0 space-y-5">
                <div class="grid grid-cols-1 gap-4 @md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label :for="`qr-color-${producto.id}`"
                            >Color del código</Label
                        >
                        <div class="flex items-center gap-2">
                            <input
                                :id="`qr-color-${producto.id}`"
                                v-model="diseno.color"
                                type="color"
                                class="h-9 w-12 cursor-pointer rounded-md border bg-background p-1"
                                @input="
                                    diseno.color = (
                                        $event.target as HTMLInputElement
                                    ).value.toUpperCase()
                                "
                            />
                            <div
                                class="flex flex-wrap gap-1.5"
                                role="group"
                                aria-label="Colores sugeridos"
                            >
                                <button
                                    v-for="c in PREDEFINIDOS"
                                    :key="c"
                                    type="button"
                                    class="size-6 rounded-full border-2 transition focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                                    :class="
                                        diseno.color === c
                                            ? 'border-primary'
                                            : 'border-transparent'
                                    "
                                    :style="{ backgroundColor: c }"
                                    :aria-label="`Usar color ${c}`"
                                    :aria-pressed="diseno.color === c"
                                    @click="diseno.color = c"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label :for="`qr-fondo-${producto.id}`">Fondo</Label>
                        <div class="flex items-center gap-3">
                            <input
                                :id="`qr-fondo-${producto.id}`"
                                v-model="diseno.fondo"
                                type="color"
                                :disabled="diseno.fondo_transparente"
                                class="h-9 w-12 cursor-pointer rounded-md border bg-background p-1 disabled:opacity-40"
                                @input="
                                    diseno.fondo = (
                                        $event.target as HTMLInputElement
                                    ).value.toUpperCase()
                                "
                            />
                            <label class="flex items-center gap-2 text-sm">
                                <Switch v-model="diseno.fondo_transparente" />
                                Transparente
                            </label>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label :for="`qr-margen-${producto.id}`">Margen</Label>
                        <SelectNativo
                            :id="`qr-margen-${producto.id}`"
                            v-model="diseno.margen"
                            :opciones="MARGENES"
                        />
                    </div>

                    <div class="grid gap-2">
                        <span class="text-sm font-medium"
                            >Logotipo al centro</span
                        >
                        <label
                            class="flex h-9 items-center gap-2 text-sm"
                            :class="!tieneLogo && 'text-muted-foreground'"
                        >
                            <Switch
                                v-model="diseno.incluir_logo"
                                :disabled="!tieneLogo"
                            />
                            {{
                                tieneLogo
                                    ? 'Logotipo del negocio'
                                    : 'El negocio no tiene logotipo cargado'
                            }}
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 @md:grid-cols-2">
                    <fieldset class="grid gap-2">
                        <legend class="mb-2 text-sm font-medium">
                            Esquinas
                        </legend>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                v-for="estilo in ESTILOS_OJOS"
                                :key="estilo.value"
                                type="button"
                                class="presionable group flex flex-col items-center gap-1.5 rounded-lg border p-2 text-xs transition hover:border-primary/60 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                                :class="
                                    diseno.estilo_ojos === estilo.value
                                        ? 'border-primary bg-primary/5 text-foreground ring-1 ring-primary'
                                        : 'text-muted-foreground'
                                "
                                :aria-pressed="
                                    diseno.estilo_ojos === estilo.value
                                "
                                @click="diseno.estilo_ojos = estilo.value"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    class="size-8 transition-transform group-hover:scale-110"
                                    aria-hidden="true"
                                >
                                    <path
                                        :d="`${estilo.marco}M7,5H17A2,2 0 0 1 19,7V17A2,2 0 0 1 17,19H7A2,2 0 0 1 5,17V7A2,2 0 0 1 7,5Z`"
                                        fill="currentColor"
                                        fill-rule="evenodd"
                                    />
                                    <rect
                                        x="8.5"
                                        y="8.5"
                                        width="7"
                                        height="7"
                                        :rx="
                                            estilo.value === 'cuadrado'
                                                ? 1
                                                : 2.2
                                        "
                                        fill="currentColor"
                                    />
                                </svg>
                                {{ estilo.label }}
                            </button>
                        </div>
                    </fieldset>

                    <fieldset class="grid gap-2">
                        <legend class="mb-2 text-sm font-medium">
                            Puntos del código
                        </legend>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                v-for="estilo in ESTILOS_MODULOS"
                                :key="estilo.value"
                                type="button"
                                class="presionable group flex flex-col items-center gap-1.5 rounded-lg border p-2 text-xs transition hover:border-primary/60 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                                :class="
                                    diseno.estilo_modulos === estilo.value
                                        ? 'border-primary bg-primary/5 text-foreground ring-1 ring-primary'
                                        : 'text-muted-foreground'
                                "
                                :aria-pressed="
                                    diseno.estilo_modulos === estilo.value
                                "
                                @click="diseno.estilo_modulos = estilo.value"
                            >
                                <svg
                                    viewBox="0 0 25 25"
                                    class="size-8 transition-transform group-hover:scale-110"
                                    aria-hidden="true"
                                >
                                    <template
                                        v-for="[x, y] in MUESTRA_MODULOS"
                                        :key="`${x}-${y}`"
                                    >
                                        <circle
                                            v-if="estilo.value === 'puntos'"
                                            :cx="x * 5 + 2.5"
                                            :cy="y * 5 + 2.5"
                                            r="2.3"
                                            fill="currentColor"
                                        />
                                        <rect
                                            v-else
                                            :x="x * 5"
                                            :y="y * 5"
                                            width="5"
                                            height="5"
                                            :rx="
                                                estilo.value === 'redondeado'
                                                    ? 2.2
                                                    : 0
                                            "
                                            fill="currentColor"
                                        />
                                    </template>
                                </svg>
                                {{ estilo.label }}
                            </button>
                        </div>
                    </fieldset>
                </div>

                <p
                    v-if="!legible"
                    class="flex items-start gap-2 rounded-lg bg-warning-soft p-3 text-sm text-warning"
                    role="alert"
                >
                    <CircleAlert class="mt-0.5 size-4 shrink-0" />
                    El contraste es insuficiente: muchos teléfonos no podrían
                    leer el código. Usa un color de código más oscuro que el
                    fondo.
                </p>
                <p
                    v-else-if="diseno.fondo_transparente"
                    class="text-xs text-muted-foreground"
                >
                    Con fondo transparente, imprime el código sobre una
                    superficie clara y lisa.
                </p>
                <p
                    v-if="diseno.margen === 'ninguno'"
                    class="text-xs text-warning"
                >
                    Sin margen, deja al imprimir un espacio libre alrededor del
                    código (al menos el ancho de cuatro módulos): muchos
                    lectores lo necesitan.
                </p>
                <p
                    v-if="diseno.estilo_modulos === 'puntos'"
                    class="text-xs text-muted-foreground"
                >
                    Con puntos, algunos lectores antiguos tardan más en leer el
                    código. Prueba la lectura con un teléfono antes de imprimir.
                </p>
                <p
                    v-if="diseno.incluir_logo && tieneLogo"
                    class="text-xs text-muted-foreground"
                >
                    Con logotipo se usa corrección de errores alta y el logo
                    ocupa como máximo el 20 % del ancho. Prueba la lectura con
                    un teléfono antes de imprimir.
                </p>

                <div
                    class="flex flex-col gap-3 border-t pt-5 @md:flex-row @md:items-end"
                >
                    <div class="grid flex-1 gap-2">
                        <Label :for="`qr-tamano-${producto.id}`"
                            >Tamaño de exportación</Label
                        >
                        <SelectNativo
                            :id="`qr-tamano-${producto.id}`"
                            v-model="tamano"
                            :opciones="TAMANOS"
                        />
                    </div>
                    <div class="flex gap-2">
                        <Button
                            type="button"
                            :disabled="!legible || descargando !== null"
                            @click="descargar('png')"
                        >
                            <Spinner v-if="descargando === 'png'" />
                            <Download v-else /> PNG
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="!legible || descargando !== null"
                            @click="descargar('svg')"
                        >
                            <Spinner v-if="descargando === 'svg'" />
                            <Download v-else /> SVG
                        </Button>
                    </div>
                </div>
                <p class="-mt-3 text-xs text-muted-foreground">
                    El PNG se genera con el tamaño elegido; el SVG es vectorial
                    y se puede ampliar sin perder calidad.
                </p>
                <InputError :message="errorDescarga ?? undefined" />

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="restablecer"
                    >
                        <RotateCcw /> QR negro tradicional
                    </Button>
                    <Button
                        v-if="puedeGuardar"
                        type="button"
                        variant="secondary"
                        size="sm"
                        :disabled="!modificado || !legible || guardando"
                        @click="guardar"
                    >
                        <Spinner v-if="guardando" />
                        <Save v-else />
                        Guardar diseño
                    </Button>
                </div>
                <div v-if="Object.keys(erroresGuardado).length" role="alert">
                    <InputError
                        v-for="(mensaje, campo) in erroresGuardado"
                        :key="campo"
                        :message="mensaje"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
