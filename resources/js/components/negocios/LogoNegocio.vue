<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, ImagePlus, Trash2, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, useTemplateRef } from 'vue';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import Panel from '@/components/comun/Panel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    destroy as eliminarLogo,
    store as subirLogo,
} from '@/routes/admin/negocios/logo';
import type { Negocio } from '@/types';

/**
 * Logotipo del negocio: validación previa en el navegador, vista previa,
 * confirmación y progreso de carga. El servidor vuelve a validar todo
 * (LogoNegocioRequest) y recodifica la imagen.
 */
const props = withDefaults(
    defineProps<{ negocio: Negocio; puedeEditar?: boolean }>(),
    { puedeEditar: true },
);

const TIPOS = ['image/png', 'image/jpeg', 'image/webp'];
const TAMANO_MAXIMO = 1024 * 1024;
const LADO_MINIMO = 64;
const LADO_MAXIMO = 4000;
const MENSAJE_GENERAL =
    'No pudimos subir la imagen. Verifica que sea PNG, JPG o WEBP y que no supere 1 MB.';

const selector = useTemplateRef<HTMLInputElement>('selector');
const archivo = ref<File | null>(null);
const vistaPrevia = ref<string | null>(null);
const error = ref<string | undefined>();
const subiendo = ref(false);
const progreso = ref<number | null>(null);
const dialogoQuitar = ref(false);
const quitando = ref(false);

const imagenVisible = computed(
    () => vistaPrevia.value ?? props.negocio.logo_url ?? null,
);

const liberarVistaPrevia = () => {
    if (vistaPrevia.value) {
        URL.revokeObjectURL(vistaPrevia.value);
    }

    vistaPrevia.value = null;
    archivo.value = null;

    if (selector.value) {
        selector.value.value = '';
    }
};

onBeforeUnmount(liberarVistaPrevia);

const medir = (url: string) =>
    new Promise<{ ancho: number; alto: number } | null>((resolver) => {
        const imagen = new Image();
        imagen.onload = () =>
            resolver({
                ancho: imagen.naturalWidth,
                alto: imagen.naturalHeight,
            });
        imagen.onerror = () => resolver(null);
        imagen.src = url;
    });

const alElegir = async (evento: Event) => {
    const elegido = (evento.target as HTMLInputElement).files?.[0];
    liberarVistaPrevia();
    error.value = undefined;

    if (!elegido) {
        return;
    }

    if (!TIPOS.includes(elegido.type)) {
        error.value = 'El logotipo debe ser una imagen PNG, JPG o WEBP.';

        return;
    }

    if (elegido.size > TAMANO_MAXIMO) {
        const mb = (elegido.size / 1024 / 1024).toLocaleString('es-MX', {
            maximumFractionDigits: 1,
        });
        error.value = `La imagen pesa ${mb} MB. El máximo permitido es 1 MB.`;

        return;
    }

    const url = URL.createObjectURL(elegido);
    const medidas = await medir(url);

    if (!medidas) {
        URL.revokeObjectURL(url);
        error.value =
            'No pudimos leer la imagen: el archivo parece dañado. Prueba con otro.';

        return;
    }

    if (
        Math.min(medidas.ancho, medidas.alto) < LADO_MINIMO ||
        Math.max(medidas.ancho, medidas.alto) > LADO_MAXIMO
    ) {
        URL.revokeObjectURL(url);
        error.value = `El logotipo debe medir entre ${LADO_MINIMO} y ${LADO_MAXIMO} píxeles por lado (mide ${medidas.ancho} × ${medidas.alto}).`;

        return;
    }

    archivo.value = elegido;
    vistaPrevia.value = url;
};

const confirmarSubida = () => {
    if (!archivo.value) {
        return;
    }

    router.post(
        subirLogo.url(props.negocio.id),
        { logo: archivo.value },
        {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => {
                subiendo.value = true;
                progreso.value = 0;
                error.value = undefined;
            },
            onProgress: (avance) => {
                progreso.value = avance?.percentage ?? null;
            },
            onSuccess: () => liberarVistaPrevia(),
            onError: (errores) => {
                // Se conserva la vista previa para que pueda corregirse.
                error.value =
                    errores.logo ?? errores.archivo ?? MENSAJE_GENERAL;
            },
            onFinish: () => {
                subiendo.value = false;
                progreso.value = null;
            },
        },
    );
};

const quitar = () => {
    router.delete(eliminarLogo.url(props.negocio.id), {
        preserveScroll: true,
        onStart: () => (quitando.value = true),
        onFinish: () => (quitando.value = false),
        onSuccess: () => (dialogoQuitar.value = false),
    });
};
</script>

<template>
    <Panel
        titulo="Logotipo del negocio"
        descripcion="Opcional, para colocarlo al centro de sus QR."
    >
        <div class="flex items-center gap-4">
            <div
                class="relative flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-[repeating-conic-gradient(var(--muted)_0%_25%,transparent_0%_50%)] bg-[length:12px_12px]"
                :class="vistaPrevia && 'ring-2 ring-primary ring-offset-2'"
            >
                <img
                    v-if="imagenVisible"
                    :src="imagenVisible"
                    :alt="
                        vistaPrevia
                            ? 'Vista previa del nuevo logotipo'
                            : `Logotipo de ${negocio.nombre_comercial}`
                    "
                    class="size-full object-contain"
                />
                <ImagePlus
                    v-else
                    class="size-6 text-muted-foreground"
                    aria-hidden="true"
                />
            </div>

            <div class="min-w-0 space-y-2">
                <input
                    ref="selector"
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    class="sr-only"
                    :id="`logo-${negocio.id}`"
                    tabindex="-1"
                    @change="alElegir"
                />

                <template v-if="vistaPrevia && puedeEditar">
                    <p class="text-sm font-medium">
                        ¿Usar esta imagen como logotipo?
                    </p>
                    <p class="truncate text-xs text-muted-foreground">
                        {{ archivo?.name }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            :disabled="subiendo"
                            @click="confirmarSubida"
                        >
                            <Spinner v-if="subiendo" />
                            <Check v-else />
                            {{
                                subiendo
                                    ? progreso !== null
                                        ? `Subiendo… ${progreso} %`
                                        : 'Subiendo…'
                                    : 'Guardar logotipo'
                            }}
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            :disabled="subiendo"
                            @click="liberarVistaPrevia"
                        >
                            <X /> Cancelar
                        </Button>
                    </div>
                </template>

                <template v-else-if="puedeEditar">
                    <div class="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            @click="selector?.click()"
                        >
                            <ImagePlus />
                            {{
                                negocio.tiene_logo
                                    ? 'Reemplazar'
                                    : 'Subir logotipo'
                            }}
                        </Button>
                        <Button
                            v-if="negocio.tiene_logo"
                            size="sm"
                            variant="ghost"
                            class="text-destructive hover:text-destructive"
                            @click="dialogoQuitar = true"
                        >
                            <Trash2 /> Quitar
                        </Button>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        PNG, JPG o WEBP · máx. 1 MB · mínimo 64 × 64 px.
                    </p>
                </template>

                <p v-else class="text-xs text-muted-foreground">
                    {{
                        negocio.tiene_logo
                            ? 'Logotipo registrado.'
                            : 'Sin logotipo registrado.'
                    }}
                </p>
            </div>
        </div>

        <div
            v-if="subiendo && progreso !== null"
            class="mt-3 h-1.5 overflow-hidden rounded-full bg-muted"
            role="progressbar"
            :aria-valuenow="progreso"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-label="Progreso de carga del logotipo"
        >
            <div
                class="h-full bg-primary transition-[width]"
                :style="{ width: `${progreso}%` }"
            />
        </div>

        <InputError class="mt-2" :message="error" />

        <DialogoConfirmacion
            v-model:open="dialogoQuitar"
            titulo="¿Quitar el logotipo?"
            descripcion="Los QR que lo incluían se generarán sin logotipo a partir de ahora."
            confirmar="Quitar logotipo"
            destructivo
            :procesando="quitando"
            @confirmar="quitar"
        />
    </Panel>
</template>
