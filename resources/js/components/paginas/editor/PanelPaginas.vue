<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    Copy,
    FilePlus2,
    House,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import CampoFormulario from '@/components/comun/CampoFormulario.vue';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Bloque, Subpagina } from '../tipos';
import { duplicarBloque, uuid } from './bloques';
import { usarEditor } from './contexto';

/**
 * Subpáginas dentro de la misma página de enlaces (p. ej. «Menú»,
 * «Promociones»). Cada una tiene su dirección /{tu-pagina}/{subpagina}.
 */
const props = defineProps<{
    reservadas: string[];
    limite: number;
    prefijo: string;
}>();

const editor = usarEditor();
const paginas = computed(() => editor.documento.value.paginas);
const actual = computed(
    () =>
        paginas.value.find((p) => p.slug === editor.paginaActual.value) ??
        paginas.value[0],
);
const porEliminar = ref<Subpagina | null>(null);
const err = (campo: string) =>
    actual.value
        ? editor.errores.value[`paginas.${actual.value.id}.${campo}`]
        : undefined;

const slugDe = (texto: string) =>
    texto
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 40);

const slugUnico = (base: string, propia?: string) => {
    let slug = slugDe(base) || 'pagina';

    if (props.reservadas.includes(slug)) {
        slug = `${slug}-1`;
    }

    const usados = new Set(
        paginas.value.filter((p) => p.id !== propia).map((p) => p.slug),
    );
    let n = 2;
    const raiz = slug;

    while (usados.has(slug)) {
        slug = `${raiz.slice(0, 36)}-${n++}`;
    }

    return slug;
};

const seleccionar = (slug: string) => {
    editor.paginaActual.value = slug;
};

const agregar = () => {
    const titulo = `Página ${paginas.value.length + 1}`;
    const nueva: Subpagina = {
        id: uuid(),
        slug: slugUnico(titulo),
        titulo,
        descripcion: '',
        bloques: [],
    };
    paginas.value.push(nueva);
    seleccionar(nueva.slug);
};

const duplicar = (p: Subpagina) => {
    const copia: Subpagina = {
        id: uuid(),
        titulo: `${p.titulo} (copia)`,
        slug: slugUnico(`${p.slug}-copia`),
        descripcion: p.descripcion,
        bloques: p.bloques.map(duplicarBloque),
    };
    paginas.value.splice(paginas.value.indexOf(p) + 1, 0, copia);
    seleccionar(copia.slug);
};

const quitarEnlaces = (bloques: Bloque[], id: string) => {
    for (const b of bloques) {
        if (b.tipo === 'subpagina' && b.pagina_id === id) {
            b.pagina_id = '';
        }

        quitarEnlaces(b.bloques ?? [], id);
    }
};

const eliminar = () => {
    const p = porEliminar.value;

    if (!p) {
        return;
    }

    editor.documento.value.paginas = paginas.value.filter((x) => x.id !== p.id);
    editor.documento.value.paginas.forEach((x) =>
        quitarEnlaces(x.bloques, p.id),
    );

    if (editor.paginaActual.value === p.slug) {
        seleccionar('inicio');
    }

    porEliminar.value = null;
};

const mover = (i: number, d: -1 | 1) => {
    const j = i + d;

    if (j < 1 || j >= paginas.value.length || i < 1) {
        return;
    }

    const lista = paginas.value;
    [lista[i], lista[j]] = [lista[j], lista[i]];
};

const cambiarSlug = (valor: string | number) => {
    if (actual.value && actual.value.slug !== 'inicio') {
        const nuevo = slugUnico(String(valor), actual.value.id);
        actual.value.slug = nuevo;
        editor.paginaActual.value = nuevo;
    }
};
</script>

<template>
    <div class="grid grid-cols-1 gap-5">
        <div>
            <h3 class="text-sm font-semibold">Tus páginas</h3>
            <p class="text-xs text-muted-foreground">
                Organiza contenido en secciones como «Menú» o «Promociones» y
                enlázalas desde tu inicio.
            </p>
        </div>

        <ul class="grid grid-cols-1 gap-1.5">
            <li v-for="(p, i) in paginas" :key="p.id">
                <div
                    class="flex items-center gap-1 rounded-lg border pr-1"
                    :class="
                        p.slug === editor.paginaActual.value
                            ? 'border-primary bg-primary/5'
                            : 'bg-card'
                    "
                >
                    <button
                        type="button"
                        class="flex min-h-11 min-w-0 flex-1 items-center gap-2 px-3 text-left"
                        @click="seleccionar(p.slug)"
                    >
                        <House
                            v-if="p.slug === 'inicio'"
                            class="size-4 shrink-0 text-muted-foreground"
                        />
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium">{{
                                p.titulo || 'Sin título'
                            }}</span>
                            <span
                                class="block truncate font-mono text-[11px] text-muted-foreground"
                                >/{{ p.slug === 'inicio' ? '' : p.slug }}</span
                            >
                        </span>
                        <span
                            class="ml-auto shrink-0 text-xs text-muted-foreground tabular-nums"
                            >{{ p.bloques.length }}</span
                        >
                    </button>
                    <template v-if="p.slug !== 'inicio' && editor.editable">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-9"
                            aria-label="Subir"
                            :disabled="i <= 1"
                            @click="mover(i, -1)"
                            ><ArrowUp
                        /></Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-9"
                            aria-label="Bajar"
                            :disabled="i === paginas.length - 1"
                            @click="mover(i, 1)"
                            ><ArrowDown
                        /></Button>
                    </template>
                </div>
            </li>
        </ul>

        <Button
            v-if="editor.editable"
            type="button"
            variant="outline"
            class="h-11"
            :disabled="paginas.length >= limite"
            @click="agregar"
        >
            <FilePlus2 /> Nueva página
        </Button>

        <fieldset
            v-if="actual"
            class="grid grid-cols-1 gap-4 rounded-xl border p-4"
            :disabled="!editor.editable"
        >
            <legend class="px-1 text-xs font-semibold text-muted-foreground">
                Página seleccionada
            </legend>
            <CampoFormulario
                etiqueta="Nombre"
                para="pagina-titulo"
                :error="err('titulo')"
            >
                <Input
                    id="pagina-titulo"
                    v-model="actual.titulo"
                    maxlength="60"
                    class="h-11"
                />
            </CampoFormulario>
            <CampoFormulario
                v-if="actual.slug !== 'inicio'"
                etiqueta="Dirección"
                para="pagina-slug"
                :ayuda="`${prefijo}/${actual.slug}`"
            >
                <Input
                    id="pagina-slug"
                    :model-value="actual.slug"
                    maxlength="40"
                    class="h-11 font-mono text-sm"
                    @change="
                        cambiarSlug(($event.target as HTMLInputElement).value)
                    "
                />
            </CampoFormulario>
            <CampoFormulario
                etiqueta="Descripción para buscadores"
                para="pagina-descripcion"
                opcional
            >
                <Input
                    id="pagina-descripcion"
                    v-model="actual.descripcion"
                    maxlength="160"
                    class="h-11"
                />
            </CampoFormulario>
            <div
                v-if="actual.slug !== 'inicio' && editor.editable"
                class="flex flex-wrap gap-2"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="h-11"
                    :disabled="paginas.length >= limite"
                    @click="duplicar(actual)"
                    ><Copy /> Duplicar</Button
                >
                <Button
                    type="button"
                    variant="ghost"
                    class="h-11 text-destructive"
                    @click="porEliminar = actual"
                    ><Trash2 /> Eliminar</Button
                >
            </div>
        </fieldset>

        <DialogoConfirmacion
            :open="porEliminar !== null"
            :titulo="`¿Eliminar la página «${porEliminar?.titulo ?? ''}»?`"
            descripcion="Se eliminarán sus elementos y los botones que llevaban a ella quedarán sin destino. Puedes deshacerlo."
            confirmar="Eliminar página"
            destructivo
            @update:open="(v: boolean) => !v && (porEliminar = null)"
            @confirmar="eliminar"
        />
    </div>
</template>
