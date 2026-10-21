<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { refDebounced } from '@vueuse/core';
import { CircleAlert, Download, ImageOff, RotateCcw, Save } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import SelectNativo from '@/components/comun/SelectNativo.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { show as qrShow, update as qrUpdate } from '@/routes/productos/qr';
import type { OpcionesQr, Producto } from '@/types';

/**
 * Diseño, vista previa y descarga del QR de un producto. El QR siempre
 * contiene la URL permanente y nunca lleva marca de DASTI.
 */
const props = defineProps<{
    producto: Producto;
    opciones: OpcionesQr;
}>();

const diseno = reactive<OpcionesQr>({ ...props.opciones });
const tamano = ref('1024');
const cargando = ref(true);
const errorVista = ref(false);
const guardando = ref(false);

const tieneLogo = computed(() => props.producto.negocio?.tiene_logo ?? false);

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
    ...extra,
});

const vistaPrevia = computed(() =>
    qrShow.url(props.producto.id, {
        query: consulta({ formato: 'svg', tamano: 512 }),
    }),
);
const vistaPreviaEstable = refDebounced(vistaPrevia, 250);

watch(vistaPreviaEstable, () => {
    cargando.value = true;
    errorVista.value = false;
});

const descarga = (formato: 'png' | 'svg') =>
    qrShow.url(props.producto.id, {
        query: consulta({
            formato,
            tamano: Number(tamano.value),
            descargar: 1,
        }),
    });

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
    });

const guardar = () => {
    router.put(qrUpdate.url(props.producto.id), consulta(), {
        preserveScroll: true,
        onStart: () => (guardando.value = true),
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
                        v-show="!errorVista"
                        :src="vistaPreviaEstable"
                        :alt="`Vista previa del QR de ${producto.nombre}`"
                        class="size-full object-contain"
                        @load="cargando = false"
                        @error="
                            cargando = false;
                            errorVista = true;
                        "
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
                    >
                        <ImageOff class="size-6" />
                        No se pudo generar la vista previa con este diseño.
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
                            >Tamaño de exportación (PNG)</Label
                        >
                        <SelectNativo
                            :id="`qr-tamano-${producto.id}`"
                            v-model="tamano"
                            :opciones="TAMANOS"
                        />
                    </div>
                    <div class="flex gap-2">
                        <Button
                            as-child
                            :disabled="!legible"
                            :class="
                                !legible && 'pointer-events-none opacity-50'
                            "
                        >
                            <a
                                :href="descarga('png')"
                                download
                                :aria-disabled="!legible"
                                ><Download /> PNG</a
                            >
                        </Button>
                        <Button
                            variant="outline"
                            as-child
                            :class="
                                !legible && 'pointer-events-none opacity-50'
                            "
                        >
                            <a
                                :href="descarga('svg')"
                                download
                                :aria-disabled="!legible"
                                ><Download /> SVG</a
                            >
                        </Button>
                    </div>
                </div>

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
            </div>
        </div>
    </div>
</template>
