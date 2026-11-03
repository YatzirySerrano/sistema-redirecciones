<script setup lang="ts" generic="T extends string | number">
/** Opciones excluyentes como botones (grupo de radio accesible). */
const modelo = defineModel<T>({ required: true });

defineProps<{
    etiqueta: string;
    opciones: { value: T; label: string }[];
    deshabilitado?: boolean;
}>();
</script>

<template>
    <div class="grid grid-cols-1 gap-1.5">
        <span class="text-xs font-medium text-muted-foreground">{{
            etiqueta
        }}</span>
        <div
            role="radiogroup"
            :aria-label="etiqueta"
            class="flex flex-wrap gap-1 rounded-lg bg-muted p-1"
        >
            <button
                v-for="o in opciones"
                :key="String(o.value)"
                type="button"
                role="radio"
                :aria-checked="modelo === o.value"
                :disabled="deshabilitado"
                class="min-h-10 flex-1 rounded-md px-2.5 text-xs font-medium whitespace-nowrap transition disabled:cursor-not-allowed disabled:opacity-60"
                :class="
                    modelo === o.value
                        ? 'bg-background text-foreground shadow-xs'
                        : 'text-muted-foreground hover:text-foreground'
                "
                @click="modelo = o.value"
            >
                {{ o.label }}
            </button>
        </div>
    </div>
</template>
