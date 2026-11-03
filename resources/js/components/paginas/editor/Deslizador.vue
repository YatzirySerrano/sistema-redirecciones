<script setup lang="ts">
/** Valor numérico con deslizador y lectura del valor. */
const modelo = defineModel<number>({ required: true });

defineProps<{
    etiqueta: string;
    id: string;
    min: number;
    max: number;
    paso?: number;
    unidad?: string;
    deshabilitado?: boolean;
}>();
</script>

<template>
    <div class="grid grid-cols-1 gap-1">
        <div class="flex items-center justify-between text-xs">
            <label :for="id" class="font-medium text-muted-foreground">{{
                etiqueta
            }}</label>
            <span class="font-mono text-muted-foreground tabular-nums"
                >{{ modelo }}{{ unidad }}</span
            >
        </div>
        <input
            :id="id"
            v-model.number="modelo"
            type="range"
            :min="min"
            :max="max"
            :step="paso ?? 1"
            :disabled="deshabilitado"
            class="h-10 w-full cursor-pointer accent-primary disabled:cursor-not-allowed"
        />
    </div>
</template>
