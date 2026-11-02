<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { CirclePause, CirclePlay } from '@lucide/vue';
import { computed, ref } from 'vue';
import AccionFila from '@/components/comun/AccionFila.vue';
import CampoFormulario from '@/components/comun/CampoFormulario.vue';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';

/**
 * Suspender o reactivar un negocio o un acceso de usuario, con motivo
 * opcional (queda en la auditoría). Usado en fichas y listados.
 */
const props = withDefaults(
    defineProps<{
        url: string;
        estado: string;
        nombre: string;
        tipo: 'negocio' | 'usuario';
        compacto?: boolean;
    }>(),
    { compacto: false },
);

const TEXTOS = {
    negocio: {
        suspender:
            'Sus enlaces dejarán de redirigir y sus usuarios no podrán entrar hasta que se reactive.',
        reactivar:
            'Sus enlaces volverán a redirigir y sus usuarios podrán entrar de nuevo.',
    },
    usuario: {
        suspender:
            'Se cerrarán sus sesiones abiertas y no podrá entrar hasta que se reactive.',
        reactivar: 'Podrá volver a iniciar sesión con su contraseña.',
    },
} as const;

const abierto = ref(false);
const suspender = computed(() => props.estado === 'activo');
const form = useForm({ estado: '', motivo: '' });
const error = computed(
    () =>
        form.errors.motivo ??
        form.errors.estado ??
        (form.errors as Record<string, string | undefined>).usuario,
);

const confirmar = () => {
    form.estado = suspender.value ? 'suspendido' : 'activo';
    form.transform((d) => ({ ...d, motivo: d.motivo.trim() || null })).patch(
        props.url,
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
            suspender ? `¿Suspender «${nombre}»?` : `¿Reactivar «${nombre}»?`
        "
        :descripcion="
            suspender ? TEXTOS[tipo].suspender : TEXTOS[tipo].reactivar
        "
        :confirmar="suspender ? 'Suspender' : 'Reactivar'"
        :destructivo="suspender"
        :procesando="form.processing"
        @confirmar="confirmar"
    >
        <template #disparador>
            <AccionFila
                v-if="compacto"
                :etiqueta="suspender ? 'Suspender' : 'Reactivar'"
                :icono="suspender ? CirclePause : CirclePlay"
                :destructiva="suspender"
                compacta
            />
            <Button v-else :variant="suspender ? 'outline' : 'default'">
                <CirclePause v-if="suspender" />
                <CirclePlay v-else />
                {{ suspender ? 'Suspender' : 'Reactivar' }}
            </Button>
        </template>
        <CampoFormulario
            etiqueta="Motivo (opcional)"
            :para="`motivo-${tipo}-${nombre}`"
            :error="error"
            ayuda="Queda registrado en la auditoría."
        >
            <Textarea
                :id="`motivo-${tipo}-${nombre}`"
                v-model="form.motivo"
                rows="2"
                maxlength="255"
            />
        </CampoFormulario>
    </DialogoConfirmacion>
</template>
