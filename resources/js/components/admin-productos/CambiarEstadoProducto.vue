<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { CirclePause, CirclePlay } from '@lucide/vue';
import { computed, ref } from 'vue';
import CampoFormulario from '@/components/comun/CampoFormulario.vue';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { estado as rutaEstado } from '@/routes/admin/productos';
import type { Producto } from '@/types';

const props = defineProps<{ producto: Producto }>();

const abierto = ref(false);
const suspender = computed(() => props.producto.estado === 'activo');

const form = useForm({ estado: '', motivo: '' });

const confirmar = () => {
    form.estado = suspender.value ? 'suspendido' : 'activo';
    form.transform((d) => ({ ...d, motivo: d.motivo.trim() || null })).patch(
        rutaEstado.url(props.producto.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                abierto.value = false;
                form.reset();
            },
        },
    );
};
</script>

<template>
    <DialogoConfirmacion
        v-model:open="abierto"
        :titulo="
            suspender
                ? `¿Suspender «${producto.nombre}»?`
                : `¿Reactivar «${producto.nombre}»?`
        "
        :descripcion="
            suspender
                ? 'El enlace permanente dejará de redirigir y mostrará «no disponible». El cliente no podrá cambiar el destino hasta que se reactive.'
                : 'El enlace permanente volverá a redirigir al destino configurado y el cliente podrá administrarlo de nuevo.'
        "
        :confirmar="suspender ? 'Suspender producto' : 'Reactivar producto'"
        :destructivo="suspender"
        :procesando="form.processing"
        @confirmar="confirmar"
    >
        <template #disparador>
            <Button :variant="suspender ? 'outline' : 'default'">
                <CirclePause v-if="suspender" />
                <CirclePlay v-else />
                {{ suspender ? 'Suspender' : 'Reactivar' }}
            </Button>
        </template>
        <CampoFormulario
            etiqueta="Motivo"
            para="motivo-estado"
            :error="form.errors.motivo || form.errors.estado"
            opcional
            ayuda="Queda registrado en la auditoría."
        >
            <Textarea
                id="motivo-estado"
                v-model="form.motivo"
                rows="2"
                maxlength="255"
            />
        </CampoFormulario>
    </DialogoConfirmacion>
</template>
