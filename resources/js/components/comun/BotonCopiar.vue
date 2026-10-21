<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useCopiar } from '@/composables/useCopiar';

const props = withDefaults(
    defineProps<{
        texto: string;
        etiqueta?: string;
        mensaje?: string;
        soloIcono?: boolean;
        variante?: 'outline' | 'ghost' | 'secondary' | 'default';
    }>(),
    {
        etiqueta: 'Copiar',
        mensaje: 'Copiado al portapapeles',
        soloIcono: false,
        variante: 'outline',
    },
);

const { copiado, copiar } = useCopiar();
</script>

<template>
    <Button
        type="button"
        :variant="variante"
        :size="soloIcono ? 'icon' : 'sm'"
        :aria-label="soloIcono ? etiqueta : undefined"
        :title="soloIcono ? etiqueta : undefined"
        @click="copiar(props.texto, props.mensaje)"
    >
        <Check v-if="copiado" class="text-success" />
        <Copy v-else />
        <span v-if="!soloIcono">{{ copiado ? 'Copiado' : etiqueta }}</span>
    </Button>
</template>
