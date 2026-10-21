<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    CircleAlert,
    CircleCheck,
    ExternalLink,
    Info,
    LockKeyhole,
    Save,
} from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { fechaHora, relativo } from '@/lib/formato';
import { comprobarUrl } from '@/lib/urlDestino';
import type { Producto } from '@/types';

/**
 * Edición del destino de un producto (cliente y administrador). La
 * comprobación en vivo solo orienta: el servidor valida y decide.
 */
const props = withDefaults(
    defineProps<{
        producto: Producto;
        accion: string;
        obligatorio?: boolean;
        compacto?: boolean;
    }>(),
    { obligatorio: true, compacto: false },
);

const emit = defineEmits<{ guardado: [] }>();

const form = useForm({ url_destino: props.producto.url_destino ?? '' });

const comprobacion = computed(() => comprobarUrl(form.url_destino));
const sinCambios = computed(
    () => form.url_destino.trim() === (props.producto.url_destino ?? ''),
);
const puedeGuardar = computed(
    () =>
        !form.processing &&
        !sinCambios.value &&
        (!props.obligatorio || form.url_destino.trim() !== ''),
);

const guardar = () => {
    form.put(props.accion, {
        preserveScroll: true,
        onSuccess: () => {
            form.defaults({ url_destino: form.url_destino.trim() });
            form.url_destino = form.url_destino.trim();
            emit('guardado');
        },
    });
};
</script>

<template>
    <form class="space-y-4" novalidate @submit.prevent="guardar">
        <div class="grid gap-2">
            <Label :for="`destino-${producto.id}`">Nueva URL de destino</Label>
            <Input
                :id="`destino-${producto.id}`"
                v-model="form.url_destino"
                type="url"
                inputmode="url"
                autocomplete="url"
                spellcheck="false"
                placeholder="https://www.instagram.com/tu-negocio"
                maxlength="2048"
                :aria-invalid="!!form.errors.url_destino || undefined"
                :aria-describedby="`ayuda-destino-${producto.id}`"
                class="h-11 font-mono text-sm"
            />
            <InputError :message="form.errors.url_destino" />

            <div
                :id="`ayuda-destino-${producto.id}`"
                aria-live="polite"
                class="min-h-5 text-xs"
            >
                <p
                    v-if="!comprobacion && !obligatorio"
                    class="text-muted-foreground"
                >
                    Déjalo vacío para que el enlace muestre «no disponible».
                </p>
                <p
                    v-else-if="comprobacion && !comprobacion.valida"
                    class="flex items-center gap-1.5 text-warning"
                >
                    <CircleAlert class="size-3.5" /> {{ comprobacion.motivo }}
                </p>
                <div
                    v-else-if="comprobacion?.valida"
                    class="flex flex-wrap items-center gap-x-3 gap-y-1 text-muted-foreground"
                >
                    <span class="flex items-center gap-1.5 text-success">
                        <CircleCheck class="size-3.5" /> Lleva a
                        <strong class="font-semibold">{{
                            comprobacion.dominio
                        }}</strong>
                    </span>
                    <span
                        v-if="!comprobacion.segura"
                        class="flex items-center gap-1 text-warning"
                    >
                        <CircleAlert class="size-3.5" /> Sin conexión segura
                        (http)
                    </span>
                    <span v-else class="flex items-center gap-1"
                        ><LockKeyhole class="size-3.5" /> Conexión segura</span
                    >
                    <a
                        :href="form.url_destino.trim()"
                        target="_blank"
                        rel="noopener noreferrer nofollow"
                        class="inline-flex items-center gap-1 font-medium text-primary hover:underline"
                    >
                        Abrir para comprobar <ExternalLink class="size-3" />
                    </a>
                </div>
            </div>
        </div>

        <div
            v-if="!compacto"
            class="flex gap-3 rounded-lg border border-primary/15 bg-accent/60 p-3 text-sm text-accent-foreground"
        >
            <Info class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <p>
                Cambiar el destino <strong>no modifica el QR impreso</strong> ni
                requiere <strong>volver a programar la tarjeta NFC</strong>: el
                enlace permanente sigue siendo el mismo y empezará a llevar a la
                nueva dirección en cuanto guardes.
            </p>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-muted-foreground">
                <template v-if="producto.destino_actualizado_en">
                    Última actualización:
                    <time
                        :datetime="producto.destino_actualizado_en"
                        :title="fechaHora(producto.destino_actualizado_en)"
                        >{{ relativo(producto.destino_actualizado_en) }}</time
                    >
                </template>
                <template v-else>Aún no se ha configurado un destino.</template>
            </p>
            <div class="flex items-center gap-2">
                <Button
                    v-if="!sinCambios && !form.processing"
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="form.reset()"
                >
                    Descartar
                </Button>
                <Button type="submit" :disabled="!puedeGuardar">
                    <Spinner v-if="form.processing" />
                    <Save v-else />
                    Guardar destino
                </Button>
            </div>
        </div>
    </form>
</template>
