<script setup lang="ts">
import { Film, ImageIcon, X } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { urlMedio } from '../tema';
import type { Medio } from '../tipos';
import BibliotecaMedios from './BibliotecaMedios.vue';

/** Campo de imagen o video: vista en miniatura, elegir y quitar. */
const modelo = defineModel<Medio | null | undefined>({ default: null });

withDefaults(
    defineProps<{
        etiqueta: string;
        tipo?: 'imagen' | 'video';
        uso?: 'normal' | 'fondo' | 'favicon' | 'miniatura';
        ayuda?: string;
        error?: string;
        deshabilitado?: boolean;
    }>(),
    { tipo: 'imagen', uso: 'normal' },
);

const abierto = ref(false);
</script>

<template>
    <div class="grid grid-cols-1 gap-1.5">
        <span class="text-xs font-medium text-muted-foreground">{{
            etiqueta
        }}</span>
        <div class="flex items-center gap-3">
            <button
                type="button"
                class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-muted disabled:cursor-not-allowed"
                :aria-label="`${etiqueta}: elegir`"
                :disabled="deshabilitado"
                @click="abierto = true"
            >
                <img
                    v-if="tipo === 'imagen' && urlMedio(modelo)"
                    :src="urlMedio(modelo) ?? undefined"
                    alt=""
                    class="size-full object-cover"
                />
                <Film
                    v-else-if="tipo === 'video' && modelo"
                    class="size-5 text-primary"
                />
                <ImageIcon v-else class="size-5 text-muted-foreground" />
            </button>
            <div class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="h-11"
                    :disabled="deshabilitado"
                    @click="abierto = true"
                >
                    {{ modelo ? 'Cambiar' : 'Elegir' }}
                </Button>
                <Button
                    v-if="modelo"
                    type="button"
                    variant="ghost"
                    class="h-11"
                    :disabled="deshabilitado"
                    @click="modelo = null"
                >
                    <X /> Quitar
                </Button>
            </div>
        </div>
        <p v-if="ayuda && !error" class="text-xs text-muted-foreground">
            {{ ayuda }}
        </p>
        <InputError :message="error" />
        <BibliotecaMedios
            v-model:open="abierto"
            :tipo="tipo"
            :uso="uso"
            :editable="!deshabilitado"
            @elegir="modelo = $event"
        />
    </div>
</template>
