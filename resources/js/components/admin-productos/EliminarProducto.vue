<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import { Button } from '@/components/ui/button';
import { destroy } from '@/routes/admin/productos';
import type { Producto } from '@/types';

const props = defineProps<{ producto: Producto }>();

const abierto = ref(false);
const procesando = ref(false);

const eliminar = () => {
    router.delete(destroy.url(props.producto.id), {
        onStart: () => (procesando.value = true),
        onFinish: () => (procesando.value = false),
        onSuccess: () => (abierto.value = false),
    });
};
</script>

<template>
    <DialogoConfirmacion
        v-model:open="abierto"
        :titulo="`¿Eliminar «${producto.nombre}»?`"
        descripcion="Solo se permite porque está pendiente de preparación y nunca ha recibido accesos. Su enlace permanente dejará de existir. Esta acción no se puede deshacer."
        confirmar="Eliminar definitivamente"
        destructivo
        :procesando="procesando"
        @confirmar="eliminar"
    >
        <template #disparador>
            <Button
                variant="ghost"
                class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                ><Trash2 /> Eliminar</Button
            >
        </template>
    </DialogoConfirmacion>
</template>
