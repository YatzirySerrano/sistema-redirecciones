<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    Bold,
    CalendarClock,
    CircleCheck,
    Italic,
    Plus,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import CampoFormulario from '@/components/comun/CampoFormulario.vue';
import SelectNativo from '@/components/comun/SelectNativo.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { REDES } from '../catalogo';
import { interpretarInsertado } from '../contenido';
import type { Bloque } from '../tipos';
import { uuid } from './bloques';
import CampoColor from './CampoColor.vue';
import { usarEditor } from './contexto';
import ListaBloques from './ListaBloques.vue';
import SelectorIcono from './SelectorIcono.vue';
import SelectorMedio from './SelectorMedio.vue';

/** Campos de un elemento según su tipo. */
const bloque = defineModel<Bloque>({ required: true });

const editor = usarEditor();
const id = computed(() => bloque.value.id);
const err = (campo: string) =>
    editor.errores.value[`bloques.${id.value}.${campo}`];
const errElemento = (elemento: string, campo: string) =>
    editor.errores.value[`bloques.${id.value}.elementos.${elemento}.${campo}`];
const f = (campo: string) => `b-${id.value}-${campo}`;
const deshabilitado = computed(() => !editor.editable);

const esBoton = computed(() =>
    ['enlace', 'whatsapp', 'telefono', 'correo', 'subpagina'].includes(
        bloque.value.tipo,
    ),
);
const coloresPropios = ref(
    !!(bloque.value.color_fondo || bloque.value.color_texto),
);
const alternarColores = (activo: boolean) => {
    coloresPropios.value = activo;

    if (activo) {
        bloque.value.color_fondo =
            editor.documento.value.tema.colores.boton_fondo;
        bloque.value.color_texto =
            editor.documento.value.tema.colores.boton_texto;
    } else {
        bloque.value.color_fondo = null;
        bloque.value.color_texto = null;
    }
};

const otrasPaginas = computed(() =>
    editor.documento.value.paginas
        .filter((p) => p.slug !== editor.paginaActual.value)
        .map((p) => ({ value: p.id, label: p.titulo || p.slug })),
);

const insertado = computed(() =>
    (bloque.value.tipo === 'video' && bloque.value.fuente !== 'archivo') ||
    bloque.value.tipo === 'musica'
        ? interpretarInsertado(
              bloque.value.url ?? '',
              bloque.value.tipo === 'musica' ? 'musica' : 'video',
          )
        : null,
);
// La vista previa usa el proveedor interpretado en el navegador; el
// servidor lo vuelve a interpretar al guardar.
const alCambiarInsertado = () => {
    bloque.value.proveedor = insertado.value?.proveedor ?? '';
    bloque.value.ref = insertado.value?.ref ?? '';
};

const NOMBRES_PROVEEDOR: Record<string, string> = {
    youtube: 'YouTube',
    vimeo: 'Vimeo',
    spotify: 'Spotify',
    soundcloud: 'SoundCloud',
    apple: 'Apple Music',
};

// Formato del texto enriquecido.
const areaTexto = ref<HTMLTextAreaElement | null>(null);
const formato = (marca: '**' | '_') => {
    const area =
        (areaTexto.value as unknown as { $el?: HTMLTextAreaElement } | null)
            ?.$el ?? areaTexto.value;
    const texto = bloque.value.texto ?? '';

    if (!(area instanceof HTMLTextAreaElement)) {
        bloque.value.texto = `${texto}${marca}texto${marca}`;

        return;
    }

    const [inicio, fin] = [area.selectionStart, area.selectionEnd];
    const elegido = texto.slice(inicio, fin) || 'texto';
    bloque.value.texto = `${texto.slice(0, inicio)}${marca}${elegido}${marca}${texto.slice(fin)}`;
};

const OPCIONES = {
    animacion: [
        { value: 'tema', label: 'La del diseño' },
        { value: 'ninguna', label: 'Ninguna' },
        { value: 'elevar', label: 'Elevar al pasar' },
        { value: 'pulso', label: 'Pulso suave' },
        { value: 'brillo', label: 'Destello' },
    ],
    tamanoBoton: [
        { value: 'normal', label: 'Normal' },
        { value: 'grande', label: 'Grande' },
    ],
    alineacion: [
        { value: 'tema', label: 'La del diseño' },
        { value: 'izquierda', label: 'Izquierda' },
        { value: 'centro', label: 'Centrado' },
    ],
    proporcion: [
        { value: 'original', label: 'Original' },
        { value: 'cuadrada', label: 'Cuadrada' },
        { value: 'horizontal', label: 'Horizontal' },
        { value: 'vertical', label: 'Vertical' },
    ],
    redes: Object.entries(REDES).map(([value, r]) => ({
        value,
        label: r.etiqueta,
    })),
};

const agregarRed = () => {
    const usadas = new Set((bloque.value.elementos ?? []).map((e) => e.red));
    const red = Object.keys(REDES).find((r) => !usadas.has(r)) ?? 'sitio';
    bloque.value.elementos = [
        ...(bloque.value.elementos ?? []),
        { id: uuid(), red, valor: '', alt: '', archivo: null },
    ];
};

const agregarImagen = () => {
    bloque.value.elementos = [
        ...(bloque.value.elementos ?? []),
        { id: uuid(), red: '', archivo: null, alt: '', url: '' },
    ];
};

const moverElemento = (i: number, d: -1 | 1) => {
    const lista = [...(bloque.value.elementos ?? [])];
    const j = i + d;

    if (j < 0 || j >= lista.length) {
        return;
    }

    [lista[i], lista[j]] = [lista[j], lista[i]];
    bloque.value.elementos = lista;
};

const quitarElemento = (i: number) => {
    bloque.value.elementos = (bloque.value.elementos ?? []).filter(
        (_, k) => k !== i,
    );
};

const programar = ref(
    !!(bloque.value.visible_desde || bloque.value.visible_hasta),
);
const alternarProgramacion = (activo: boolean) => {
    programar.value = activo;

    if (!activo) {
        bloque.value.visible_desde = null;
        bloque.value.visible_hasta = null;
    }
};
</script>

<template>
    <fieldset class="grid grid-cols-1 gap-4" :disabled="deshabilitado">
        <!-- Botones de acción -->
        <template v-if="esBoton">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <CampoFormulario
                    etiqueta="Texto del botón"
                    :para="f('titulo')"
                    :error="err('titulo')"
                    class="sm:col-span-2"
                >
                    <Input
                        :id="f('titulo')"
                        v-model="bloque.titulo"
                        maxlength="100"
                        class="h-11"
                    />
                </CampoFormulario>

                <CampoFormulario
                    v-if="bloque.tipo === 'enlace'"
                    etiqueta="Dirección (URL)"
                    :para="f('url')"
                    :error="err('url')"
                    class="sm:col-span-2"
                    ayuda="Copia y pega la dirección completa del sitio."
                >
                    <Input
                        :id="f('url')"
                        v-model="bloque.url"
                        type="url"
                        inputmode="url"
                        placeholder="https://"
                        maxlength="2048"
                        class="h-11 font-mono text-sm"
                        spellcheck="false"
                    />
                </CampoFormulario>

                <template
                    v-if="
                        bloque.tipo === 'whatsapp' || bloque.tipo === 'telefono'
                    "
                >
                    <CampoFormulario
                        etiqueta="Número con lada"
                        :para="f('telefono')"
                        :error="err('telefono')"
                        ayuda="10 dígitos en México (se agrega +52) o el número completo con +código de país."
                    >
                        <Input
                            :id="f('telefono')"
                            v-model="bloque.telefono"
                            type="tel"
                            inputmode="tel"
                            placeholder="55 1234 5678"
                            maxlength="25"
                            class="h-11"
                        />
                    </CampoFormulario>
                    <CampoFormulario
                        v-if="bloque.tipo === 'whatsapp'"
                        etiqueta="Mensaje inicial"
                        :para="f('mensaje')"
                        :error="err('mensaje')"
                        opcional
                    >
                        <Textarea
                            :id="f('mensaje')"
                            v-model="bloque.mensaje"
                            rows="2"
                            maxlength="500"
                        />
                    </CampoFormulario>
                </template>

                <template v-if="bloque.tipo === 'correo'">
                    <CampoFormulario
                        etiqueta="Correo electrónico"
                        :para="f('email')"
                        :error="err('email')"
                    >
                        <Input
                            :id="f('email')"
                            v-model="bloque.email"
                            type="email"
                            inputmode="email"
                            maxlength="190"
                            class="h-11"
                        />
                    </CampoFormulario>
                    <CampoFormulario
                        etiqueta="Asunto"
                        :para="f('asunto')"
                        :error="err('asunto')"
                        opcional
                    >
                        <Input
                            :id="f('asunto')"
                            v-model="bloque.asunto"
                            maxlength="120"
                            class="h-11"
                        />
                    </CampoFormulario>
                </template>

                <CampoFormulario
                    v-if="bloque.tipo === 'subpagina'"
                    etiqueta="Página de destino"
                    :para="f('pagina_id')"
                    :error="err('pagina_id')"
                    class="sm:col-span-2"
                    :ayuda="
                        otrasPaginas.length
                            ? undefined
                            : 'Crea otra página en la pestaña «Páginas».'
                    "
                >
                    <SelectNativo
                        :id="f('pagina_id')"
                        v-model="bloque.pagina_id as string"
                        :opciones="otrasPaginas"
                        vacio="Elige una página"
                        class="[&_select]:h-11"
                    />
                </CampoFormulario>

                <CampoFormulario
                    etiqueta="Texto secundario"
                    :para="f('subtitulo')"
                    :error="err('subtitulo')"
                    opcional
                >
                    <Input
                        :id="f('subtitulo')"
                        v-model="bloque.subtitulo"
                        maxlength="140"
                        class="h-11"
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Etiqueta"
                    :para="f('insignia')"
                    opcional
                    ayuda="Ej. «Nuevo» o «-20 %»."
                >
                    <Input
                        :id="f('insignia')"
                        v-model="bloque.insignia"
                        maxlength="24"
                        class="h-11"
                    />
                </CampoFormulario>

                <div class="grid grid-cols-1 gap-1.5">
                    <span class="text-xs font-medium text-muted-foreground"
                        >Ícono</span
                    >
                    <SelectorIcono
                        v-model="bloque.icono"
                        :deshabilitado="deshabilitado"
                    />
                </div>
                <SelectorMedio
                    v-model="bloque.miniatura"
                    etiqueta="Miniatura (reemplaza al ícono)"
                    uso="miniatura"
                    :error="err('miniatura')"
                    :deshabilitado="deshabilitado"
                />

                <CampoFormulario etiqueta="Tamaño" :para="f('tamano')">
                    <SelectNativo
                        :id="f('tamano')"
                        v-model="bloque.tamano as string"
                        :opciones="OPCIONES.tamanoBoton"
                        class="[&_select]:h-11"
                    />
                </CampoFormulario>
                <CampoFormulario etiqueta="Animación" :para="f('animacion')">
                    <SelectNativo
                        :id="f('animacion')"
                        v-model="bloque.animacion as string"
                        :opciones="OPCIONES.animacion"
                        class="[&_select]:h-11"
                    />
                </CampoFormulario>
            </div>

            <div
                class="grid grid-cols-1 gap-3 rounded-lg border bg-muted/30 p-3"
            >
                <label
                    class="flex min-h-11 items-center justify-between gap-3 text-sm"
                >
                    <span
                        ><span class="font-medium">Destacar</span
                        ><span class="block text-xs text-muted-foreground"
                            >Resalta el botón con el color de acento.</span
                        ></span
                    >
                    <Switch
                        v-model="bloque.destacado"
                        :disabled="deshabilitado"
                    />
                </label>
                <label
                    class="flex min-h-11 items-center justify-between gap-3 text-sm"
                >
                    <span class="font-medium"
                        >Colores propios para este botón</span
                    >
                    <Switch
                        :model-value="coloresPropios"
                        :disabled="deshabilitado"
                        @update:model-value="alternarColores"
                    />
                </label>
                <div
                    v-if="coloresPropios"
                    class="grid grid-cols-1 gap-3 sm:grid-cols-2"
                >
                    <CampoColor
                        v-model="bloque.color_fondo as string"
                        etiqueta="Fondo del botón"
                        :id="f('color_fondo')"
                        :deshabilitado="deshabilitado"
                    />
                    <CampoColor
                        v-model="bloque.color_texto as string"
                        etiqueta="Texto del botón"
                        :id="f('color_texto')"
                        :deshabilitado="deshabilitado"
                    />
                </div>
            </div>
        </template>

        <!-- Redes sociales -->
        <template v-else-if="bloque.tipo === 'redes'">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <CampoFormulario etiqueta="Estilo" :para="f('estilo')">
                    <SelectNativo
                        :id="f('estilo')"
                        v-model="bloque.estilo as string"
                        :opciones="[
                            { value: 'iconos', label: 'Íconos en fila' },
                            { value: 'botones', label: 'Botones con nombre' },
                        ]"
                        class="[&_select]:h-11"
                    />
                </CampoFormulario>
                <CampoFormulario etiqueta="Tamaño" :para="f('tamano')">
                    <SelectNativo
                        :id="f('tamano')"
                        v-model="bloque.tamano as string"
                        :opciones="OPCIONES.tamanoBoton"
                        class="[&_select]:h-11"
                    />
                </CampoFormulario>
            </div>
            <ul class="grid grid-cols-1 gap-3">
                <li
                    v-for="(e, i) in bloque.elementos ?? []"
                    :key="e.id"
                    class="grid grid-cols-1 gap-2 rounded-lg border p-3 sm:grid-cols-[10rem_1fr_auto] sm:items-start"
                >
                    <SelectNativo
                        v-model="e.red"
                        :opciones="OPCIONES.redes"
                        etiqueta="Red social"
                        class="[&_select]:h-11"
                    />
                    <div class="grid grid-cols-1 gap-1">
                        <Input
                            v-model="e.valor"
                            :type="REDES[e.red]?.esTelefono ? 'tel' : 'url'"
                            :placeholder="REDES[e.red]?.placeholder"
                            :aria-label="`Dirección de ${REDES[e.red]?.etiqueta ?? 'la red'}`"
                            :aria-invalid="
                                !!errElemento(e.id, 'valor') || undefined
                            "
                            class="h-11"
                        />
                        <p
                            v-if="errElemento(e.id, 'valor')"
                            class="text-xs text-destructive"
                        >
                            {{ errElemento(e.id, 'valor') }}
                        </p>
                    </div>
                    <div class="flex gap-1">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-11"
                            aria-label="Subir"
                            :disabled="i === 0"
                            @click="moverElemento(i, -1)"
                            ><ArrowUp
                        /></Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-11"
                            aria-label="Bajar"
                            :disabled="
                                i === (bloque.elementos?.length ?? 0) - 1
                            "
                            @click="moverElemento(i, 1)"
                            ><ArrowDown
                        /></Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-11 text-destructive"
                            aria-label="Quitar red"
                            @click="quitarElemento(i)"
                            ><Trash2
                        /></Button>
                    </div>
                </li>
            </ul>
            <p v-if="err('elementos')" class="text-sm text-destructive">
                {{ err('elementos') }}
            </p>
            <Button
                type="button"
                variant="outline"
                class="h-11 w-fit"
                @click="agregarRed"
                ><Plus /> Agregar red</Button
            >
        </template>

        <!-- Mapa -->
        <template v-else-if="bloque.tipo === 'mapa'">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <CampoFormulario etiqueta="Título" :para="f('titulo')" opcional>
                    <Input
                        :id="f('titulo')"
                        v-model="bloque.titulo"
                        maxlength="100"
                        class="h-11"
                    />
                </CampoFormulario>
                <CampoFormulario etiqueta="Texto del botón" :para="f('boton')">
                    <Input
                        :id="f('boton')"
                        v-model="bloque.boton"
                        maxlength="40"
                        class="h-11"
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Dirección o nombre del lugar"
                    :para="f('direccion')"
                    :error="err('direccion')"
                    class="sm:col-span-2"
                    ayuda="Escríbela como la buscarías en Google Maps."
                >
                    <Input
                        :id="f('direccion')"
                        v-model="bloque.direccion"
                        maxlength="200"
                        class="h-11"
                    />
                </CampoFormulario>
            </div>
            <label
                class="flex min-h-11 items-center justify-between gap-3 rounded-lg border p-3 text-sm"
            >
                <span
                    ><span class="font-medium">Mostrar mapa</span
                    ><span class="block text-xs text-muted-foreground"
                        >El visitante lo carga con un toque (Google Maps).</span
                    ></span
                >
                <Switch
                    v-model="bloque.mostrar_mapa"
                    :disabled="deshabilitado"
                />
            </label>
        </template>

        <!-- Video y música -->
        <template
            v-else-if="bloque.tipo === 'video' || bloque.tipo === 'musica'"
        >
            <CampoFormulario
                v-if="bloque.tipo === 'video'"
                etiqueta="Origen del video"
                :para="f('fuente')"
            >
                <SelectNativo
                    :id="f('fuente')"
                    v-model="bloque.fuente as string"
                    :opciones="[
                        { value: 'externo', label: 'YouTube o Vimeo' },
                        { value: 'archivo', label: 'Subir mi video' },
                    ]"
                    class="[&_select]:h-11"
                />
            </CampoFormulario>
            <template
                v-if="bloque.tipo === 'video' && bloque.fuente === 'archivo'"
            >
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <SelectorMedio
                        v-model="bloque.archivo"
                        etiqueta="Video (MP4 o WEBM)"
                        tipo="video"
                        :error="err('archivo')"
                        :deshabilitado="deshabilitado"
                    />
                    <SelectorMedio
                        v-model="bloque.poster"
                        etiqueta="Imagen de portada"
                        :error="err('poster')"
                        :deshabilitado="deshabilitado"
                    />
                </div>
            </template>
            <CampoFormulario
                v-else
                :etiqueta="
                    bloque.tipo === 'video'
                        ? 'Enlace del video'
                        : 'Enlace de la canción, álbum o lista'
                "
                :para="f('url')"
                :error="err('url')"
                :ayuda="
                    bloque.tipo === 'video'
                        ? 'Copia el enlace de YouTube o Vimeo.'
                        : 'Copia el enlace de Spotify, SoundCloud o Apple Music.'
                "
            >
                <Input
                    :id="f('url')"
                    v-model="bloque.url"
                    type="url"
                    inputmode="url"
                    placeholder="https://"
                    class="h-11 font-mono text-sm"
                    @update:model-value="alCambiarInsertado"
                />
                <p
                    v-if="bloque.url && insertado"
                    class="flex items-center gap-1.5 text-xs text-success"
                >
                    <CircleCheck class="size-3.5" />
                    {{ NOMBRES_PROVEEDOR[insertado.proveedor] }} reconocido
                </p>
                <p v-else-if="bloque.url" class="text-xs text-warning">
                    No reconocemos este enlace. Revisa que sea de un proveedor
                    compatible.
                </p>
            </CampoFormulario>
            <CampoFormulario etiqueta="Título" :para="f('titulo')" opcional>
                <Input
                    :id="f('titulo')"
                    v-model="bloque.titulo"
                    maxlength="100"
                    class="h-11"
                />
            </CampoFormulario>
        </template>

        <!-- Imagen -->
        <template v-else-if="bloque.tipo === 'imagen'">
            <SelectorMedio
                v-model="bloque.archivo"
                etiqueta="Imagen"
                :error="err('archivo')"
                :deshabilitado="deshabilitado"
            />
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <CampoFormulario
                    etiqueta="Descripción de la imagen"
                    :para="f('alt')"
                    ayuda="Ayuda a personas con lectores de pantalla."
                >
                    <Input
                        :id="f('alt')"
                        v-model="bloque.alt"
                        maxlength="200"
                        class="h-11"
                    />
                </CampoFormulario>
                <CampoFormulario etiqueta="Proporción" :para="f('proporcion')">
                    <SelectNativo
                        :id="f('proporcion')"
                        v-model="bloque.proporcion as string"
                        :opciones="OPCIONES.proporcion"
                        class="[&_select]:h-11"
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Enlace al tocarla"
                    :para="f('url')"
                    :error="err('url')"
                    opcional
                    class="sm:col-span-2"
                >
                    <Input
                        :id="f('url')"
                        v-model="bloque.url"
                        type="url"
                        inputmode="url"
                        placeholder="https://"
                        class="h-11 font-mono text-sm"
                    />
                </CampoFormulario>
            </div>
            <label
                class="flex min-h-11 items-center justify-between gap-3 text-sm"
            >
                <span class="font-medium">Esquinas redondeadas</span>
                <Switch v-model="bloque.redondeo" :disabled="deshabilitado" />
            </label>
        </template>

        <!-- Carrusel -->
        <template v-else-if="bloque.tipo === 'carrusel'">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <CampoFormulario etiqueta="Título" :para="f('titulo')" opcional>
                    <Input
                        :id="f('titulo')"
                        v-model="bloque.titulo"
                        maxlength="100"
                        class="h-11"
                    />
                </CampoFormulario>
                <CampoFormulario etiqueta="Proporción" :para="f('proporcion')">
                    <SelectNativo
                        :id="f('proporcion')"
                        v-model="bloque.proporcion as string"
                        :opciones="OPCIONES.proporcion.slice(1)"
                        class="[&_select]:h-11"
                    />
                </CampoFormulario>
            </div>
            <ul class="grid grid-cols-1 gap-3">
                <li
                    v-for="(e, i) in bloque.elementos ?? []"
                    :key="e.id"
                    class="grid grid-cols-1 gap-3 rounded-lg border p-3"
                >
                    <div class="flex items-start justify-between gap-2">
                        <SelectorMedio
                            v-model="e.archivo"
                            :etiqueta="`Imagen ${i + 1}`"
                            :error="errElemento(e.id, 'archivo')"
                            :deshabilitado="deshabilitado"
                        />
                        <div class="flex gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-11"
                                aria-label="Subir"
                                :disabled="i === 0"
                                @click="moverElemento(i, -1)"
                                ><ArrowUp
                            /></Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-11"
                                aria-label="Bajar"
                                :disabled="
                                    i === (bloque.elementos?.length ?? 0) - 1
                                "
                                @click="moverElemento(i, 1)"
                                ><ArrowDown
                            /></Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-11 text-destructive"
                                aria-label="Quitar imagen"
                                @click="quitarElemento(i)"
                                ><Trash2
                            /></Button>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <Input
                            v-model="e.alt"
                            placeholder="Descripción de la imagen"
                            aria-label="Descripción de la imagen"
                            maxlength="200"
                            class="h-11"
                        />
                        <div class="grid grid-cols-1 gap-1">
                            <Input
                                v-model="e.url"
                                type="url"
                                placeholder="Enlace al tocarla (opcional)"
                                aria-label="Enlace al tocarla"
                                class="h-11 font-mono text-sm"
                            />
                            <p
                                v-if="errElemento(e.id, 'url')"
                                class="text-xs text-destructive"
                            >
                                {{ errElemento(e.id, 'url') }}
                            </p>
                        </div>
                    </div>
                </li>
            </ul>
            <p v-if="err('elementos')" class="text-sm text-destructive">
                {{ err('elementos') }}
            </p>
            <Button
                type="button"
                variant="outline"
                class="h-11 w-fit"
                :disabled="(bloque.elementos?.length ?? 0) >= 12"
                @click="agregarImagen"
                ><Plus /> Agregar imagen</Button
            >
        </template>

        <!-- Título -->
        <template v-else-if="bloque.tipo === 'titulo'">
            <CampoFormulario
                etiqueta="Texto"
                :para="f('texto')"
                :error="err('texto')"
            >
                <Input
                    :id="f('texto')"
                    v-model="bloque.texto"
                    maxlength="120"
                    class="h-11"
                />
            </CampoFormulario>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <CampoFormulario etiqueta="Tamaño" :para="f('tamano')">
                    <SelectNativo
                        :id="f('tamano')"
                        v-model="bloque.tamano as string"
                        :opciones="[
                            { value: 'grande', label: 'Grande' },
                            { value: 'mediano', label: 'Mediano' },
                            { value: 'pequeno', label: 'Pequeño' },
                        ]"
                        class="[&_select]:h-11"
                    />
                </CampoFormulario>
                <CampoFormulario etiqueta="Alineación" :para="f('alineacion')">
                    <SelectNativo
                        :id="f('alineacion')"
                        v-model="bloque.alineacion as string"
                        :opciones="OPCIONES.alineacion"
                        class="[&_select]:h-11"
                    />
                </CampoFormulario>
            </div>
        </template>

        <!-- Texto -->
        <template v-else-if="bloque.tipo === 'texto'">
            <CampoFormulario
                etiqueta="Texto"
                :para="f('texto')"
                :error="err('texto')"
                ayuda="Selecciona palabras y usa los botones, o escribe **negritas** y _cursivas_."
            >
                <div class="flex gap-1">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="size-11"
                        aria-label="Negritas"
                        @click="formato('**')"
                        ><Bold
                    /></Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="size-11"
                        aria-label="Cursivas"
                        @click="formato('_')"
                        ><Italic
                    /></Button>
                </div>
                <Textarea
                    :id="f('texto')"
                    ref="areaTexto"
                    v-model="bloque.texto"
                    rows="5"
                    maxlength="2000"
                />
            </CampoFormulario>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <CampoFormulario etiqueta="Tamaño" :para="f('tamano')">
                    <SelectNativo
                        :id="f('tamano')"
                        v-model="bloque.tamano as string"
                        :opciones="[
                            { value: 'pequeno', label: 'Pequeño' },
                            { value: 'normal', label: 'Normal' },
                            { value: 'grande', label: 'Grande' },
                        ]"
                        class="[&_select]:h-11"
                    />
                </CampoFormulario>
                <CampoFormulario etiqueta="Alineación" :para="f('alineacion')">
                    <SelectNativo
                        :id="f('alineacion')"
                        v-model="bloque.alineacion as string"
                        :opciones="OPCIONES.alineacion"
                        class="[&_select]:h-11"
                    />
                </CampoFormulario>
            </div>
        </template>

        <!-- Separador -->
        <template v-else-if="bloque.tipo === 'separador'">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <CampoFormulario etiqueta="Estilo" :para="f('estilo')">
                    <SelectNativo
                        :id="f('estilo')"
                        v-model="bloque.estilo as string"
                        :opciones="[
                            { value: 'linea', label: 'Línea' },
                            { value: 'puntos', label: 'Puntos' },
                            { value: 'espacio', label: 'Solo espacio' },
                        ]"
                        class="[&_select]:h-11"
                    />
                </CampoFormulario>
                <CampoFormulario
                    :etiqueta="`Alto: ${bloque.alto ?? 24} px`"
                    :para="f('alto')"
                >
                    <input
                        :id="f('alto')"
                        v-model.number="bloque.alto"
                        type="range"
                        min="8"
                        max="120"
                        step="4"
                        class="h-11 w-full accent-primary"
                    />
                </CampoFormulario>
            </div>
        </template>

        <!-- Sección / grupo -->
        <template v-else-if="bloque.tipo === 'grupo'">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <CampoFormulario
                    etiqueta="Título de la sección"
                    :para="f('titulo')"
                    opcional
                    class="sm:col-span-2"
                >
                    <Input
                        :id="f('titulo')"
                        v-model="bloque.titulo"
                        maxlength="100"
                        class="h-11"
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Disposición"
                    :para="f('disposicion')"
                >
                    <SelectNativo
                        :id="f('disposicion')"
                        v-model="bloque.disposicion as string"
                        :opciones="[
                            { value: 'lista', label: 'Lista' },
                            { value: 'cuadricula', label: 'Cuadrícula' },
                        ]"
                        class="[&_select]:h-11"
                    />
                </CampoFormulario>
                <CampoFormulario
                    v-if="bloque.disposicion === 'cuadricula'"
                    etiqueta="Columnas"
                    :para="f('columnas')"
                >
                    <SelectNativo
                        :id="f('columnas')"
                        :model-value="String(bloque.columnas ?? 2)"
                        :opciones="[
                            { value: '2', label: '2 columnas' },
                            {
                                value: '3',
                                label: '3 columnas (pantallas amplias)',
                            },
                        ]"
                        class="[&_select]:h-11"
                        @update:model-value="
                            bloque.columnas = $event === '3' ? 3 : 2
                        "
                    />
                </CampoFormulario>
            </div>
            <div class="grid grid-cols-1 gap-1 rounded-lg border p-3">
                <label
                    class="flex min-h-11 items-center justify-between gap-3 text-sm"
                >
                    <span
                        ><span class="font-medium">Plegable</span
                        ><span class="block text-xs text-muted-foreground"
                            >El visitante la abre o cierra con un toque.</span
                        ></span
                    >
                    <Switch
                        v-model="bloque.plegable"
                        :disabled="deshabilitado"
                    />
                </label>
                <label
                    v-if="bloque.plegable"
                    class="flex min-h-11 items-center justify-between gap-3 text-sm"
                >
                    <span class="font-medium">Abierta al inicio</span>
                    <Switch
                        v-model="bloque.abierto"
                        :disabled="deshabilitado"
                    />
                </label>
            </div>
            <div class="grid grid-cols-1 gap-2">
                <p class="text-sm font-medium">Elementos de la sección</p>
                <ListaBloques v-model="bloque.bloques as Bloque[]" anidada />
            </div>
        </template>

        <!-- Tarjeta -->
        <template v-else-if="bloque.tipo === 'tarjeta'">
            <CampoFormulario
                etiqueta="Título"
                :para="f('titulo')"
                :error="err('titulo')"
            >
                <Input
                    :id="f('titulo')"
                    v-model="bloque.titulo"
                    maxlength="100"
                    class="h-11"
                />
            </CampoFormulario>
            <CampoFormulario
                etiqueta="Texto"
                :para="f('texto')"
                :error="err('texto')"
                opcional
                ayuda="Admite **negritas** y _cursivas_."
            >
                <Textarea
                    :id="f('texto')"
                    v-model="bloque.texto"
                    rows="4"
                    maxlength="800"
                />
            </CampoFormulario>
            <SelectorMedio
                v-model="bloque.imagen"
                etiqueta="Imagen"
                :error="err('imagen')"
                :deshabilitado="deshabilitado"
            />
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <CampoFormulario
                    etiqueta="Texto del botón"
                    :para="f('boton')"
                    opcional
                >
                    <Input
                        :id="f('boton')"
                        v-model="bloque.boton"
                        maxlength="40"
                        class="h-11"
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Enlace del botón"
                    :para="f('url')"
                    :error="err('url')"
                    opcional
                >
                    <Input
                        :id="f('url')"
                        v-model="bloque.url"
                        type="url"
                        placeholder="https://"
                        class="h-11 font-mono text-sm"
                    />
                </CampoFormulario>
            </div>
        </template>

        <!-- Formulario de contacto -->
        <template v-else-if="bloque.tipo === 'contacto'">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <CampoFormulario etiqueta="Título" :para="f('titulo')">
                    <Input
                        :id="f('titulo')"
                        v-model="bloque.titulo"
                        maxlength="100"
                        class="h-11"
                    />
                </CampoFormulario>
                <CampoFormulario etiqueta="Texto del botón" :para="f('boton')">
                    <Input
                        :id="f('boton')"
                        v-model="bloque.boton"
                        maxlength="40"
                        class="h-11"
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Descripción"
                    :para="f('descripcion')"
                    opcional
                    class="sm:col-span-2"
                >
                    <Input
                        :id="f('descripcion')"
                        v-model="bloque.descripcion"
                        maxlength="300"
                        class="h-11"
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Aviso de privacidad"
                    :para="f('aviso')"
                    class="sm:col-span-2"
                    ayuda="Se muestra antes de enviar. Explica para qué usarás los datos."
                >
                    <Textarea
                        :id="f('aviso')"
                        v-model="bloque.aviso"
                        rows="2"
                        maxlength="400"
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Mensaje al enviar"
                    :para="f('exito')"
                    class="sm:col-span-2"
                >
                    <Input
                        :id="f('exito')"
                        v-model="bloque.exito"
                        maxlength="200"
                        class="h-11"
                    />
                </CampoFormulario>
            </div>
            <label
                class="flex min-h-11 items-center justify-between gap-3 rounded-lg border p-3 text-sm"
            >
                <span class="font-medium"
                    >Pedir teléfono (opcional para el visitante)</span
                >
                <Switch
                    v-model="bloque.pedir_telefono"
                    :disabled="deshabilitado"
                />
            </label>
            <p class="text-xs text-muted-foreground">
                Los mensajes llegan a «Mensajes» en tu panel. Protegido contra
                envíos automáticos.
            </p>
        </template>

        <!-- QR -->
        <template v-else-if="bloque.tipo === 'qr'">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <CampoFormulario etiqueta="Título" :para="f('titulo')" opcional>
                    <Input
                        :id="f('titulo')"
                        v-model="bloque.titulo"
                        maxlength="80"
                        class="h-11"
                    />
                </CampoFormulario>
                <CampoFormulario
                    :etiqueta="`Tamaño: ${bloque.tamano ?? 180} px`"
                    :para="f('tamano')"
                >
                    <input
                        :id="f('tamano')"
                        v-model.number="bloque.tamano"
                        type="range"
                        min="120"
                        max="280"
                        step="10"
                        class="h-11 w-full accent-primary"
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Descripción"
                    :para="f('descripcion')"
                    opcional
                    class="sm:col-span-2"
                >
                    <Input
                        :id="f('descripcion')"
                        v-model="bloque.descripcion"
                        maxlength="200"
                        class="h-11"
                    />
                </CampoFormulario>
            </div>
        </template>

        <!-- Programación -->
        <div class="grid grid-cols-1 gap-3 rounded-lg border border-dashed p-3">
            <label
                class="flex min-h-11 items-center justify-between gap-3 text-sm"
            >
                <span class="flex items-center gap-2 font-medium"
                    ><CalendarClock class="size-4 text-muted-foreground" />
                    Programar cuándo se muestra</span
                >
                <Switch
                    :model-value="programar"
                    :disabled="deshabilitado"
                    @update:model-value="alternarProgramacion"
                />
            </label>
            <div v-if="programar" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <CampoFormulario
                    etiqueta="Mostrar desde"
                    :para="f('desde')"
                    :error="err('visible_desde')"
                    opcional
                >
                    <Input
                        :id="f('desde')"
                        :model-value="bloque.visible_desde ?? ''"
                        type="datetime-local"
                        class="h-11"
                        @update:model-value="
                            bloque.visible_desde = $event
                                ? String($event)
                                : null
                        "
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Ocultar a partir de"
                    :para="f('hasta')"
                    :error="err('visible_hasta')"
                    opcional
                >
                    <Input
                        :id="f('hasta')"
                        :model-value="bloque.visible_hasta ?? ''"
                        type="datetime-local"
                        class="h-11"
                        @update:model-value="
                            bloque.visible_hasta = $event
                                ? String($event)
                                : null
                        "
                    />
                </CampoFormulario>
                <p class="text-xs text-muted-foreground sm:col-span-2">
                    Se usa la hora del centro de México. Fuera de estas fechas
                    el elemento no aparece en tu página.
                </p>
            </div>
        </div>
    </fieldset>
</template>
