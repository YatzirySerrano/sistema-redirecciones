<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    ChevronDown,
    CircleAlert,
    Copy,
    Ellipsis,
    Eye,
    EyeOff,
    GripVertical,
    Trash2,
} from '@lucide/vue';
import { computed, nextTick, onMounted, ref, useTemplateRef, watch } from 'vue';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { estadoProgramacion } from '../tema';
import type { Bloque } from '../tipos';
import { resumenBloque, tituloBloque, TIPOS } from './bloques';
import { usarEditor } from './contexto';
import EditorBloque from './EditorBloque.vue';

/**
 * Elemento de la lista del editor: asa para arrastrar, acciones (subir,
 * bajar, ocultar, duplicar, eliminar) y editor desplegable.
 */
const bloque = defineModel<Bloque>({ required: true });

const props = defineProps<{
    indice: number;
    total: number;
    arrastrando?: boolean;
}>();

const emit = defineEmits<{
    mover: [direccion: -1 | 1];
    duplicar: [];
    eliminar: [];
    arrastrar: [evento: PointerEvent];
    teclado: [evento: KeyboardEvent];
}>();

const editor = usarEditor();
const raiz = useTemplateRef<HTMLElement>('raiz');
const abierto = ref(editor.recienAgregado.value === bloque.value.id);
const confirmarEliminar = ref(false);
const meta = computed(() => TIPOS[bloque.value.tipo]);
const conError = computed(() => {
    const ids = editor.bloquesConError.value;

    return (
        ids.has(bloque.value.id) ||
        (bloque.value.bloques ?? []).some((b) => ids.has(b.id))
    );
});
const programacion = computed(() => estadoProgramacion(bloque.value));
const ETIQUETAS_PROGRAMACION: Record<string, { texto: string; clase: string }> =
    {
        oculto: { texto: 'Oculto', clase: 'bg-muted text-muted-foreground' },
        programado: {
            texto: 'Programado',
            clase: 'bg-primary/10 text-primary',
        },
        vencido: { texto: 'Finalizado', clase: 'bg-warning-soft text-warning' },
        temporal: { texto: 'Temporal', clase: 'bg-primary/10 text-primary' },
    };
const resumen = computed(() => resumenBloque(bloque.value));

watch(conError, (v) => {
    if (v) {
        abierto.value = true;
    }
});

onMounted(async () => {
    if (editor.recienAgregado.value === bloque.value.id) {
        await nextTick();
        raiz.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        editor.recienAgregado.value = null;
    }
});

const alternarOculto = () => {
    bloque.value.oculto = !bloque.value.oculto;
};
</script>

<template>
    <article
        ref="raiz"
        class="rounded-xl border bg-card shadow-xs transition-shadow data-[arrastrando]:shadow-lg"
        :class="[
            conError ? 'border-destructive/60 ring-1 ring-destructive/30' : '',
            bloque.oculto ? 'opacity-70' : '',
            props.arrastrando ? 'opacity-80' : '',
        ]"
    >
        <header class="flex items-center gap-1 py-1.5 pr-1.5 pl-1">
            <button
                v-if="editor.editable"
                type="button"
                class="flex h-11 w-9 shrink-0 cursor-grab touch-none items-center justify-center rounded-md text-muted-foreground hover:bg-accent active:cursor-grabbing sm:w-11"
                :aria-label="`Mover «${tituloBloque(bloque)}». Arrastra o usa las flechas arriba y abajo.`"
                @pointerdown="emit('arrastrar', $event)"
                @keydown="emit('teclado', $event)"
            >
                <GripVertical class="size-4" />
            </button>
            <button
                type="button"
                class="flex min-h-11 min-w-0 flex-1 items-center gap-3 rounded-md px-1 text-left hover:bg-accent/50"
                :aria-expanded="abierto"
                @click="abierto = !abierto"
            >
                <span
                    class="hidden size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary min-[400px]:flex"
                >
                    <component
                        :is="meta.icono"
                        class="size-4"
                        aria-hidden="true"
                    />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium">{{
                        tituloBloque(bloque)
                    }}</span>
                    <span class="block truncate text-xs text-muted-foreground">
                        {{ meta.etiqueta
                        }}<template v-if="resumen"> · {{ resumen }}</template>
                    </span>
                </span>
                <CircleAlert
                    v-if="conError"
                    class="size-4 shrink-0 text-destructive"
                    aria-label="Tiene datos por corregir"
                />
                <span
                    v-if="ETIQUETAS_PROGRAMACION[programacion]"
                    class="hidden shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium sm:inline"
                    :class="ETIQUETAS_PROGRAMACION[programacion].clase"
                    >{{ ETIQUETAS_PROGRAMACION[programacion].texto }}</span
                >
                <span
                    v-if="bloque.destacado"
                    class="hidden shrink-0 rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-medium text-primary sm:inline"
                    >Destacado</span
                >
                <EyeOff
                    v-if="bloque.oculto"
                    class="size-4 shrink-0 text-muted-foreground sm:hidden"
                    aria-label="Oculto"
                />
                <ChevronDown
                    class="size-4 shrink-0 text-muted-foreground transition-transform"
                    :class="abierto ? 'rotate-180' : ''"
                    aria-hidden="true"
                />
            </button>

            <template v-if="editor.editable">
                <!-- Subir y bajar siempre visibles: en pantallas táctiles
                     arrastrar puede ser incómodo. -->
                <div class="flex items-center">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-10 sm:size-9"
                        :aria-label="`Subir «${tituloBloque(bloque)}»`"
                        title="Subir"
                        :disabled="indice === 0"
                        @click="emit('mover', -1)"
                        ><ArrowUp
                    /></Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-10 sm:size-9"
                        :aria-label="`Bajar «${tituloBloque(bloque)}»`"
                        title="Bajar"
                        :disabled="indice === total - 1"
                        @click="emit('mover', 1)"
                        ><ArrowDown
                    /></Button>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="hidden size-9 sm:inline-flex"
                    :aria-label="
                        bloque.oculto
                            ? 'Mostrar en la página'
                            : 'Ocultar de la página'
                    "
                    :title="bloque.oculto ? 'Mostrar' : 'Ocultar'"
                    @click="alternarOculto"
                >
                    <EyeOff v-if="bloque.oculto" />
                    <Eye v-else />
                </Button>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-10 sm:size-9"
                            aria-label="Más acciones"
                            ><Ellipsis
                        /></Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            class="sm:hidden"
                            @select="alternarOculto"
                        >
                            <template v-if="bloque.oculto"
                                ><Eye /> Mostrar en la página</template
                            >
                            <template v-else
                                ><EyeOff /> Ocultar de la página</template
                            >
                        </DropdownMenuItem>
                        <DropdownMenuItem @select="emit('duplicar')"
                            ><Copy /> Duplicar</DropdownMenuItem
                        >
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            class="text-destructive focus:text-destructive"
                            @select="confirmarEliminar = true"
                            ><Trash2 /> Eliminar</DropdownMenuItem
                        >
                    </DropdownMenuContent>
                </DropdownMenu>
            </template>
        </header>

        <div v-if="abierto" class="border-t px-3 py-4 sm:px-4">
            <EditorBloque v-model="bloque" />
        </div>

        <DialogoConfirmacion
            v-model:open="confirmarEliminar"
            :titulo="`¿Eliminar «${tituloBloque(bloque)}»?`"
            descripcion="Desaparecerá de tu página cuando publiques. Puedes recuperarlo con «Deshacer» o desde el historial."
            confirmar="Eliminar"
            destructivo
            @confirmar="
                confirmarEliminar = false;
                emit('eliminar');
            "
        />
    </article>
</template>
