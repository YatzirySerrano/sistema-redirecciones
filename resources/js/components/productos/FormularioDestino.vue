<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Info, Save } from '@lucide/vue';
import { computed, toRef } from 'vue';
import InputError from '@/components/InputError.vue';
import EstadoValidacionDestino from '@/components/productos/EstadoValidacionDestino.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { fechaHora, relativo } from '@/lib/formato';
import { useValidacionDestino } from '@/composables/useValidacionDestino';
import type { Producto } from '@/types';

/**
 * Edición del destino de un producto (cliente y administrador). La
 * comprobación en vivo usa las mismas reglas del servidor, que vuelve a
 * validar al guardar.
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

const { resultado } = useValidacionDestino(toRef(form, 'url_destino'));

// Referencia para «sin cambios»: el destino guardado (ya normalizado por el
// servidor) o, tras guardar, el valor que el servidor devolvió.
const guardado = computed(() => props.producto.url_destino ?? '');
const sinCambios = computed(() => form.url_destino.trim() === guardado.value);
const puedeGuardar = computed(
    () =>
        !form.processing &&
        !sinCambios.value &&
        resultado.value.estado !== 'invalida' &&
        (!props.obligatorio || form.url_destino.trim() !== ''),
);

const guardar = () => {
    form.put(props.accion, {
        preserveScroll: true,
        onSuccess: () => {
            // El servidor puede normalizar la URL (dominios internacionales,
            // acentos en la ruta): el formulario adopta el valor guardado.
            form.defaults({ url_destino: guardado.value });
            form.url_destino = guardado.value;
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

            <EstadoValidacionDestino
                :id="`ayuda-destino-${producto.id}`"
                :resultado="resultado"
                :texto-vacio="
                    obligatorio
                        ? null
                        : 'Déjalo vacío para que el enlace muestre «no disponible».'
                "
            />
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
