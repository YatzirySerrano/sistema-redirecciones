<script setup lang="ts">
import { Check, Film, ImagePlus, Trash2, Upload } from '@lucide/vue';
import { computed, ref, useTemplateRef, watch } from 'vue';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import type { Medio } from '../tipos';
import {
    biblioteca,
    cargarBiblioteca,
    eliminarMedio,
    subirMedio,
} from './biblioteca';
import type { MedioBiblioteca } from './biblioteca';

/**
 * Elegir o subir una imagen/video. Las imágenes se revisan y optimizan en
 * el servidor (se eliminan los datos ocultos como la ubicación).
 */
const abierto = defineModel<boolean>('open', { default: false });

const props = withDefaults(
    defineProps<{
        tipo?: 'imagen' | 'video';
        uso?: 'normal' | 'fondo' | 'favicon' | 'miniatura';
        editable?: boolean;
    }>(),
    { tipo: 'imagen', uso: 'normal', editable: true },
);

const emit = defineEmits<{ elegir: [medio: Medio] }>();

const archivo = useTemplateRef<HTMLInputElement>('archivo');
const porEliminar = ref<MedioBiblioteca | null>(null);
const confirmar = ref(false);
const lista = computed(() =>
    biblioteca.medios.filter((m) => m.tipo === props.tipo),
);
const mb = (bytes: number) => `${(bytes / 1048576).toFixed(1)} MB`;

watch(abierto, (v) => {
    if (v) {
        void cargarBiblioteca();
    }
});

const alElegirArchivo = async (e: Event) => {
    const entrada = e.target as HTMLInputElement;
    const elegido = entrada.files?.[0];
    entrada.value = '';

    if (!elegido) {
        return;
    }

    const medio = await subirMedio(elegido, props.tipo, props.uso);

    if (medio) {
        emit('elegir', medio);
        abierto.value = false;
    }
};

const elegir = (medio: MedioBiblioteca) => {
    emit('elegir', {
        id: medio.id,
        url: medio.url,
        ancho: medio.ancho,
        alto: medio.alto,
    });
    abierto.value = false;
};

const pedirEliminar = (medio: MedioBiblioteca) => {
    porEliminar.value = medio;
    confirmar.value = true;
};

const eliminar = async () => {
    if (porEliminar.value && (await eliminarMedio(porEliminar.value))) {
        confirmar.value = false;
    }
};
</script>

<template>
    <Dialog v-model:open="abierto">
        <DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{
                    tipo === 'video' ? 'Tus videos' : 'Tus imágenes'
                }}</DialogTitle>
                <DialogDescription>
                    {{
                        tipo === 'video'
                            ? 'Sube un video corto MP4 o WEBM (sin sonido se reproduce mejor como fondo).'
                            : 'Sube PNG, JPG, WEBP o GIF. Las optimizamos y quitamos los datos ocultos (como la ubicación).'
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <p v-if="biblioteca.uso" class="text-xs text-muted-foreground">
                    {{ biblioteca.uso.usados }} de
                    {{ biblioteca.uso.max_archivos }} archivos ·
                    {{ mb(biblioteca.uso.bytes) }} de
                    {{ mb(biblioteca.uso.max_bytes) }}
                </p>
                <input
                    ref="archivo"
                    type="file"
                    class="sr-only"
                    :accept="
                        tipo === 'video'
                            ? 'video/mp4,video/webm'
                            : 'image/png,image/jpeg,image/webp,image/gif'
                    "
                    @change="alElegirArchivo"
                />
                <Button
                    v-if="editable"
                    type="button"
                    class="h-11"
                    :disabled="biblioteca.subiendo"
                    @click="archivo?.click()"
                >
                    <Spinner v-if="biblioteca.subiendo" />
                    <Upload v-else />
                    {{
                        biblioteca.subiendo
                            ? 'Subiendo…'
                            : tipo === 'video'
                              ? 'Subir video'
                              : 'Subir imagen'
                    }}
                </Button>
            </div>

            <div v-if="biblioteca.cargando" class="flex justify-center py-10">
                <Spinner />
            </div>
            <div
                v-else-if="lista.length === 0"
                class="flex flex-col items-center gap-2 rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
            >
                <ImagePlus class="size-8" aria-hidden="true" />
                Aún no has subido
                {{ tipo === 'video' ? 'videos' : 'imágenes' }}.
            </div>
            <ul v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <li
                    v-for="medio in lista"
                    :key="medio.id"
                    class="group relative"
                >
                    <button
                        type="button"
                        class="block w-full overflow-hidden rounded-lg border bg-muted text-left focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none"
                        @click="elegir(medio)"
                    >
                        <img
                            v-if="medio.tipo === 'imagen'"
                            :src="medio.url"
                            :alt="medio.nombre ?? ''"
                            class="aspect-square w-full object-cover"
                            loading="lazy"
                        />
                        <span
                            v-else
                            class="flex aspect-square w-full items-center justify-center"
                            ><Film class="size-8 text-muted-foreground"
                        /></span>
                        <span
                            class="block truncate px-2 py-1.5 text-xs text-muted-foreground"
                            >{{ medio.nombre ?? 'Archivo' }}</span
                        >
                        <span
                            class="absolute top-2 left-2 hidden rounded-full bg-primary p-1 text-primary-foreground group-hover:block"
                            ><Check class="size-3.5"
                        /></span>
                    </button>
                    <Button
                        v-if="editable"
                        type="button"
                        size="icon"
                        variant="secondary"
                        class="absolute top-2 right-2 size-8 opacity-90"
                        aria-label="Eliminar archivo"
                        @click="pedirEliminar(medio)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </li>
            </ul>
        </DialogContent>
    </Dialog>

    <DialogoConfirmacion
        v-model:open="confirmar"
        titulo="¿Eliminar este archivo?"
        descripcion="Si lo usas en tu página dejará de mostrarse cuando vuelvas a publicar. Esta acción no se puede deshacer."
        confirmar="Eliminar"
        destructivo
        @confirmar="eliminar"
    />
</template>
