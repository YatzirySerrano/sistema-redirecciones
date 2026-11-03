<script setup lang="ts">
import { ref, watch } from 'vue';

/**
 * Selector de color: muestra del color (selector del sistema) + código
 * hexadecimal editable. Solo acepta #rrggbb.
 */
const modelo = defineModel<string>({ required: true });

defineProps<{
    etiqueta: string;
    id: string;
    aviso?: string | null;
    deshabilitado?: boolean;
}>();

const texto = ref(modelo.value);

watch(modelo, (v) => {
    texto.value = v;
});

const confirmar = () => {
    const valor = texto.value.trim().toLowerCase();
    const completo = /^#?[0-9a-f]{6}$/.test(valor)
        ? valor.startsWith('#')
            ? valor
            : `#${valor}`
        : null;

    if (completo) {
        modelo.value = completo;
    } else {
        texto.value = modelo.value;
    }
};
</script>

<template>
    <div class="grid grid-cols-1 gap-1.5">
        <label :for="id" class="text-xs font-medium text-muted-foreground">{{
            etiqueta
        }}</label>
        <div
            class="flex h-11 items-center gap-2 rounded-lg border bg-background pr-2 pl-1.5 focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/30"
            :class="aviso ? 'border-warning' : 'border-input'"
        >
            <input
                :id="`${id}-muestra`"
                v-model="modelo"
                type="color"
                class="size-8 shrink-0 cursor-pointer rounded-md border-0 bg-transparent p-0 disabled:cursor-not-allowed"
                :aria-label="`${etiqueta}: elegir color`"
                :disabled="deshabilitado"
            />
            <input
                :id="id"
                v-model="texto"
                class="min-w-0 flex-1 bg-transparent font-mono text-sm uppercase outline-none"
                maxlength="7"
                spellcheck="false"
                autocomplete="off"
                :disabled="deshabilitado"
                @change="confirmar"
                @blur="confirmar"
                @keydown.enter.prevent="confirmar"
            />
        </div>
        <p v-if="aviso" class="text-xs text-warning">{{ aviso }}</p>
    </div>
</template>
