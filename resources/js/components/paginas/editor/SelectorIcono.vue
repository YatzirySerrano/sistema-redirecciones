<script setup lang="ts">
import { Ban } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { ICONOS, REDES, componenteIcono } from '../catalogo';
import { ICONOS_REDES } from '../iconosRedes';

/** Ícono del botón: lista curada + glifos de redes sociales. */
const modelo = defineModel<string | null>({ default: null });

defineProps<{ deshabilitado?: boolean }>();

const abierto = ref(false);
const actual = computed(() => componenteIcono(modelo.value));
const nombre = computed(() => {
    const v = modelo.value;

    if (!v) {
        return 'Sin ícono';
    }

    return v.startsWith('red:')
        ? (REDES[v.slice(4)]?.etiqueta ?? 'Red')
        : (ICONOS[v]?.etiqueta ?? 'Ícono');
});

const elegir = (valor: string | null) => {
    modelo.value = valor;
    abierto.value = false;
};
</script>

<template>
    <Popover v-model:open="abierto">
        <PopoverTrigger as-child>
            <Button
                type="button"
                variant="outline"
                class="h-11 w-full justify-start gap-2"
                :disabled="deshabilitado"
            >
                <component :is="actual" v-if="actual" class="size-4" />
                <Ban v-else class="size-4 text-muted-foreground" />
                <span class="truncate">{{ nombre }}</span>
            </Button>
        </PopoverTrigger>
        <PopoverContent
            class="w-[min(22rem,calc(100vw-2rem))] p-3"
            align="start"
        >
            <p class="mb-2 text-xs font-medium text-muted-foreground">Íconos</p>
            <div class="grid grid-cols-7 gap-1">
                <button
                    type="button"
                    class="flex size-10 items-center justify-center rounded-md hover:bg-accent"
                    :class="!modelo ? 'bg-accent ring-1 ring-primary' : ''"
                    title="Sin ícono"
                    aria-label="Sin ícono"
                    @click="elegir(null)"
                >
                    <Ban class="size-4 text-muted-foreground" />
                </button>
                <button
                    v-for="(icono, clave) in ICONOS"
                    :key="clave"
                    type="button"
                    class="flex size-10 items-center justify-center rounded-md hover:bg-accent"
                    :class="
                        modelo === clave ? 'bg-accent ring-1 ring-primary' : ''
                    "
                    :title="icono.etiqueta"
                    :aria-label="icono.etiqueta"
                    @click="elegir(clave)"
                >
                    <component :is="icono.icono" class="size-4" />
                </button>
            </div>
            <p class="mt-3 mb-2 text-xs font-medium text-muted-foreground">
                Redes sociales
            </p>
            <div class="grid grid-cols-7 gap-1">
                <button
                    v-for="(icono, clave) in ICONOS_REDES"
                    :key="clave"
                    type="button"
                    class="flex size-10 items-center justify-center rounded-md hover:bg-accent"
                    :class="
                        modelo === `red:${clave}`
                            ? 'bg-accent ring-1 ring-primary'
                            : ''
                    "
                    :title="REDES[clave]?.etiqueta"
                    :aria-label="REDES[clave]?.etiqueta"
                    @click="elegir(`red:${clave}`)"
                >
                    <component :is="icono" class="size-4" />
                </button>
            </div>
        </PopoverContent>
    </Popover>
</template>
