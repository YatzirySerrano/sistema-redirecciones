<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/**
 * Confirmación de acciones sensibles o destructivas. El contenido extra
 * (p. ej. un motivo) se pasa por el slot por defecto.
 */
const abierto = defineModel<boolean>('open', { default: false });

withDefaults(
    defineProps<{
        titulo: string;
        descripcion?: string;
        confirmar?: string;
        destructivo?: boolean;
        procesando?: boolean;
        deshabilitado?: boolean;
    }>(),
    {
        confirmar: 'Confirmar',
        destructivo: false,
        procesando: false,
        deshabilitado: false,
    },
);

const emit = defineEmits<{ confirmar: [] }>();
</script>

<template>
    <AlertDialog v-model:open="abierto">
        <AlertDialogTrigger v-if="$slots.disparador" as-child>
            <slot name="disparador" />
        </AlertDialogTrigger>
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ titulo }}</AlertDialogTitle>
                <AlertDialogDescription v-if="descripcion">{{
                    descripcion
                }}</AlertDialogDescription>
            </AlertDialogHeader>
            <slot />
            <AlertDialogFooter>
                <AlertDialogCancel :disabled="procesando"
                    >Cancelar</AlertDialogCancel
                >
                <Button
                    :variant="destructivo ? 'destructive' : 'default'"
                    :disabled="procesando || deshabilitado"
                    @click="emit('confirmar')"
                >
                    <Spinner v-if="procesando" />
                    {{ confirmar }}
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
