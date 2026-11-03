<script setup lang="ts">
import { HeartHandshake, LayoutList, Nfc, Sparkles } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{ clave: string }>();

const icono = computed(
    () =>
        ({
            redirecciones: Nfc,
            fidelidad: HeartHandshake,
            pagina_enlaces: LayoutList,
        })[props.clave] ?? Sparkles,
);
</script>

<template>
    <span
        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/15"
        aria-hidden="true"
    >
        <component :is="icono" class="size-5" />
    </span>
</template>
