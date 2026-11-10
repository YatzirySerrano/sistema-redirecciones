<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { computed, watch } from 'vue';
import CampoFormulario from '@/components/comun/CampoFormulario.vue';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Producto } from '@/types';

/**
 * Cambia solo el nombre descriptivo del producto (permiso
 * «productos.editar_nombre»). El enlace permanente, el QR y los destinos no
 * cambian; el servidor ignora cualquier otro dato.
 */
const props = defineProps<{ producto: Producto; accion: string }>();

const abierto = defineModel<boolean>('open', { default: false });
const form = useForm({ nombre: props.producto.nombre });

watch(abierto, (valor) => {
    if (valor) {
        form.clearErrors();
        form.nombre = props.producto.nombre;
    }
});

const sinCambio = computed(
    () => form.nombre.trim() === props.producto.nombre.trim(),
);

const guardar = () => {
    if (sinCambio.value || form.processing) {
        return;
    }

    form.transform((d) => ({ nombre: d.nombre.trim() })).patch(props.accion, {
        preserveScroll: true,
        onSuccess: () => {
            abierto.value = false;
        },
    });
};
</script>

<template>
    <DialogoConfirmacion
        v-model:open="abierto"
        titulo="Cambiar el nombre"
        descripcion="Un nombre para reconocer este producto en tu panel, por ejemplo «Mostrador principal» o «Reseñas Google». No cambia el enlace, el código QR ni a dónde lleva."
        confirmar="Guardar nombre"
        :deshabilitado="sinCambio || form.nombre.trim() === ''"
        :procesando="form.processing"
        @confirmar="guardar"
    >
        <template #disparador>
            <Button variant="outline" size="sm">
                <Pencil /> Cambiar nombre
            </Button>
        </template>
        <CampoFormulario
            etiqueta="Nombre"
            para="nombre_producto"
            :error="form.errors.nombre"
        >
            <Input
                id="nombre_producto"
                v-model="form.nombre"
                maxlength="110"
                autocomplete="off"
                @keydown.enter.prevent="guardar"
            />
        </CampoFormulario>
    </DialogoConfirmacion>
</template>
