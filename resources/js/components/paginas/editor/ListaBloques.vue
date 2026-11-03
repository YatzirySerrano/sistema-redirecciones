<script setup lang="ts">
import { Plus } from '@lucide/vue';
import { useTemplateRef } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Bloque, TipoBloque } from '../tipos';
import { duplicarBloque, nuevoBloque, TIPOS } from './bloques';
import { usarEditor } from './contexto';
import TarjetaBloque from './TarjetaBloque.vue';
import { mover, useReordenable } from './useReordenable';

/**
 * Lista ordenable de elementos (de una página o de una sección).
 */
const bloques = defineModel<Bloque[]>({ required: true });

const props = defineProps<{ anidada?: boolean }>();

const editor = usarEditor();
const contenedor = useTemplateRef<HTMLElement>('contenedor');
const TIPOS_EN_GRUPO: TipoBloque[] = [
    'enlace',
    'whatsapp',
    'telefono',
    'correo',
    'subpagina',
    'imagen',
];

const moverA = (desde: number, hasta: number) => {
    const copia = [...bloques.value];
    mover(copia, desde, hasta);
    bloques.value = copia;
};

const { arrastrando, indicador, iniciar, teclado } = useReordenable(
    contenedor,
    moverA,
);

const duplicar = (i: number) => {
    const copia = duplicarBloque(bloques.value[i]);
    const lista = [...bloques.value];
    lista.splice(i + 1, 0, copia);
    bloques.value = lista;
    editor.recienAgregado.value = copia.id;
};

const eliminar = (i: number) => {
    bloques.value = bloques.value.filter((_, k) => k !== i);
};

const agregar = (tipo: TipoBloque) => {
    const nuevo = nuevoBloque(tipo);
    bloques.value = [...bloques.value, nuevo];
    editor.recienAgregado.value = nuevo.id;
};
</script>

<template>
    <div class="grid grid-cols-1 gap-2">
        <div
            ref="contenedor"
            class="grid grid-cols-1 gap-2"
            :class="props.anidada ? 'rounded-lg bg-muted/40 p-2' : ''"
        >
            <template v-for="(b, i) in bloques" :key="b.id">
                <div
                    v-if="indicador === i && arrastrando !== null"
                    class="h-1 rounded-full bg-primary"
                    aria-hidden="true"
                />
                <div data-reordenable class="relative min-w-0">
                    <TarjetaBloque
                        v-model="bloques[i]"
                        :indice="i"
                        :total="bloques.length"
                        :arrastrando="arrastrando === i"
                        @mover="(d) => moverA(i, i + d)"
                        @duplicar="duplicar(i)"
                        @eliminar="eliminar(i)"
                        @arrastrar="iniciar($event, i)"
                        @teclado="teclado($event, i, bloques.length)"
                    />
                </div>
            </template>
            <div
                v-if="indicador === bloques.length && arrastrando !== null"
                class="h-1 rounded-full bg-primary"
                aria-hidden="true"
            />
            <p
                v-if="bloques.length === 0"
                class="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground"
            >
                {{
                    props.anidada
                        ? 'Esta sección aún no tiene elementos.'
                        : 'Tu página aún no tiene elementos. Agrega el primero.'
                }}
            </p>
        </div>

        <template v-if="editor.editable">
            <DropdownMenu v-if="props.anidada">
                <DropdownMenuTrigger as-child>
                    <Button type="button" variant="outline" class="h-11 w-fit"
                        ><Plus /> Agregar a la sección</Button
                    >
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start">
                    <DropdownMenuItem
                        v-for="tipo in TIPOS_EN_GRUPO"
                        :key="tipo"
                        @select="agregar(tipo)"
                    >
                        <component :is="TIPOS[tipo].icono" />
                        {{ TIPOS[tipo].etiqueta }}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <Button
                v-else
                type="button"
                variant="outline"
                class="h-12 w-full border-dashed"
                @click="editor.abrirCatalogo()"
            >
                <Plus /> Agregar elemento
            </Button>
        </template>
    </div>
</template>
