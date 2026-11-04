<script setup lang="ts">
import { ImageUp, Images, Trash2 } from '@lucide/vue';
import { computed, ref, useId } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import InfoArchivo from '@/components/comun/InfoArchivo.vue';
import {
    mensajeErrorSubida,
    useLimitesArchivos,
    validarArchivo,
} from '@/lib/archivos';
import { ErrorPeticion } from '@/lib/peticion';
import { store as subirImagen } from '@/routes/cliente/fidelidad/diseno/imagenes';
import { enviar } from './api';
import type { ArchivoImagen } from './tipos';

/**
 * Imagen del diseño: subir una nueva (el servidor valida el contenido real,
 * la re-codifica y quita metadatos como la ubicación) o elegir una ya
 * subida por el negocio. Solo guarda la referencia (UUID) en el documento.
 */
const modelo = defineModel<string | null>({ required: true });

const props = defineProps<{
    etiqueta: string;
    ayuda?: string;
    uso: 'logo' | 'portada' | 'fondo';
    archivos: ArchivoImagen[];
    /** URL de subida (la entrega el controlador en `rutas`). */
    url?: string;
}>();

const emit = defineEmits<{ subida: [archivo: ArchivoImagen] }>();

const id = useId();
const entrada = ref<HTMLInputElement | null>(null);
const subiendo = ref(false);
const error = ref<string | null>(null);
const biblioteca = ref(false);
const actual = computed(
    () => props.archivos.find((a) => a.id === modelo.value) ?? null,
);
const { limite } = useLimitesArchivos();
const tipoLimite = computed(() => (props.uso === 'logo' ? 'logo' : 'imagen'));
const datosLimite = computed(() => limite(tipoLimite.value));
const RECOMENDACION: Record<typeof props.uso, string> = {
    logo: 'Recomendado: cuadrado, de al menos 256 × 256 px y con fondo transparente.',
    portada: 'Recomendado: horizontal (3:1), de al menos 1600 × 530 px.',
    fondo: 'Recomendado: al menos 1600 px por su lado mayor.',
};

const elegido = async (evento: Event) => {
    const archivo = (evento.target as HTMLInputElement).files?.[0];
    // Se limpia para poder elegir otro archivo (o el mismo) sin recargar.
    (evento.target as HTMLInputElement).value = '';
    error.value = null;

    if (!archivo) {
        return;
    }

    const problema = await validarArchivo(archivo, datosLimite.value);

    if (problema) {
        error.value = problema;

        return;
    }

    const datos = new FormData();
    datos.append('imagen', archivo);
    datos.append('uso', props.uso);
    subiendo.value = true;

    try {
        const r = await enviar<{ archivo: ArchivoImagen }>(
            props.url ?? subirImagen.url(),
            'POST',
            datos,
        );
        emit('subida', r.archivo);
        modelo.value = r.archivo.id;
    } catch (e) {
        error.value =
            e instanceof ErrorPeticion
                ? e.estado === 0
                    ? e.message
                    : mensajeErrorSubida(e.estado, datosLimite.value, e.message)
                : 'No pudimos subir la imagen. Inténtalo nuevamente.';
    } finally {
        subiendo.value = false;
    }
};
</script>

<template>
    <div class="space-y-2">
        <div class="flex items-center justify-between gap-2">
            <p :id="id" class="text-sm">
                {{ etiqueta }}
                <span
                    v-if="ayuda"
                    class="block text-xs text-muted-foreground"
                    >{{ ayuda }}</span
                >
            </p>
        </div>
        <div class="flex items-center gap-3" :aria-labelledby="id">
            <div
                class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-[repeating-conic-gradient(#e7e5e4_0_25%,#fff_0_50%)] bg-[length:12px_12px]"
            >
                <img
                    v-if="actual"
                    :src="actual.url"
                    alt="Imagen elegida"
                    class="size-full object-cover"
                />
                <Images
                    v-else
                    class="size-5 text-muted-foreground"
                    aria-hidden="true"
                />
            </div>
            <div class="flex min-w-0 flex-wrap gap-1.5">
                <input
                    ref="entrada"
                    type="file"
                    class="sr-only"
                    :accept="datosLimite.aceptar"
                    tabindex="-1"
                    @change="elegido"
                />
                <Button
                    type="button"
                    size="sm"
                    class="h-10"
                    variant="outline"
                    :disabled="subiendo"
                    @click="entrada?.click()"
                >
                    <Spinner v-if="subiendo" />
                    <ImageUp v-else />
                    {{ subiendo ? 'Subiendo…' : 'Subir' }}
                </Button>
                <Button
                    v-if="archivos.length"
                    type="button"
                    size="sm"
                    class="h-10"
                    variant="ghost"
                    :aria-expanded="biblioteca"
                    @click="biblioteca = !biblioteca"
                >
                    <Images /> Mis imágenes
                </Button>
                <Button
                    v-if="modelo"
                    type="button"
                    size="sm"
                    class="h-10"
                    variant="ghost"
                    aria-label="Quitar imagen"
                    @click="modelo = null"
                >
                    <Trash2 />
                </Button>
            </div>
        </div>
        <InfoArchivo :tipo="tipoLimite" :recomendacion="RECOMENDACION[uso]" />
        <p v-if="error" class="text-xs text-destructive" role="alert">
            {{ error }}
        </p>
        <div
            v-if="biblioteca"
            class="grid max-h-48 grid-cols-4 gap-2 overflow-y-auto rounded-lg border p-2"
        >
            <button
                v-for="a in archivos"
                :key="a.id"
                type="button"
                class="aspect-square overflow-hidden rounded-md border-2 transition focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none"
                :class="
                    a.id === modelo
                        ? 'border-primary'
                        : 'border-transparent hover:border-primary/40'
                "
                :aria-pressed="a.id === modelo"
                aria-label="Usar esta imagen"
                @click="
                    modelo = a.id;
                    biblioteca = false;
                "
            >
                <img
                    :src="a.url"
                    alt=""
                    class="size-full object-cover"
                    loading="lazy"
                />
            </button>
        </div>
    </div>
</template>
