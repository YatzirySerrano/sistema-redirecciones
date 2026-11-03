<script setup lang="ts">
import { ref, useId, watch } from 'vue';
import { esHex } from '@/components/fidelidad-diseno/color';

/**
 * Color en formato #rrggbb: selector del sistema + campo de texto. Un valor
 * escrito incompleto no se aplica hasta que es válido.
 */
const modelo = defineModel<string>({ required: true });

defineProps<{ etiqueta: string; ayuda?: string }>();

const id = useId();
const texto = ref(modelo.value);

watch(modelo, (v) => (texto.value = v));

const aplicar = () => {
    const limpio = texto.value.trim().toLowerCase();
    const completo = /^#?[0-9a-f]{6}$/.test(limpio)
        ? limpio.startsWith('#')
            ? limpio
            : `#${limpio}`
        : null;

    if (completo && esHex(completo)) {
        modelo.value = completo;
    }
};
</script>

<template>
    <div class="flex items-center justify-between gap-3">
        <label :for="id" class="min-w-0 text-sm">
            <span class="block truncate">{{ etiqueta }}</span>
            <span v-if="ayuda" class="block text-xs text-muted-foreground">{{
                ayuda
            }}</span>
        </label>
        <div
            class="flex h-9 shrink-0 items-center gap-1.5 rounded-lg border border-input bg-background pr-2 pl-1 focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/30"
        >
            <input
                type="color"
                class="size-7 cursor-pointer rounded-md border-0 bg-transparent p-0 disabled:cursor-not-allowed"
                :value="modelo"
                :aria-label="`${etiqueta}: elegir color`"
                @input="modelo = ($event.target as HTMLInputElement).value"
            />
            <input
                :id="id"
                v-model="texto"
                type="text"
                inputmode="text"
                maxlength="7"
                spellcheck="false"
                autocomplete="off"
                class="w-[4.6rem] bg-transparent font-mono text-xs uppercase outline-none"
                @input="aplicar"
                @blur="texto = modelo"
            />
        </div>
    </div>
</template>
