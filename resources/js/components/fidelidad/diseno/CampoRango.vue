<script setup lang="ts">
import { useId } from 'vue';

const modelo = defineModel<number>({ required: true });

withDefaults(
    defineProps<{
        etiqueta: string;
        min: number;
        max: number;
        paso?: number;
        unidad?: string;
    }>(),
    { paso: 1, unidad: '' },
);

const id = useId();
</script>

<template>
    <div class="space-y-1.5">
        <div class="flex items-center justify-between text-sm">
            <label :for="id">{{ etiqueta }}</label>
            <span class="text-xs text-muted-foreground tabular-nums"
                >{{ modelo }}{{ unidad }}</span
            >
        </div>
        <input
            :id="id"
            v-model.number="modelo"
            type="range"
            :min="min"
            :max="max"
            :step="paso"
            class="h-2 w-full cursor-pointer accent-primary disabled:cursor-not-allowed"
        />
    </div>
</template>
