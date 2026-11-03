<script setup lang="ts">
import { Plus, RotateCcw, TriangleAlert, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import DialogoConfirmacion from '@/components/comun/DialogoConfirmacion.vue';
import SelectNativo from '@/components/comun/SelectNativo.vue';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { FUENTES, TEXTURAS } from '../catalogo';
import { avisosContraste, estiloFondo, variablesTema } from '../tema';
import type { ColoresTema, Documento, Tema } from '../tipos';
import { duplicarBloque } from './bloques';
import CampoColor from './CampoColor.vue';
import { usarEditor } from './contexto';
import ControlSegmentado from './ControlSegmentado.vue';
import Deslizador from './Deslizador.vue';
import SelectorMedio from './SelectorMedio.vue';

/**
 * Diseño: plantillas, colores (con avisos de contraste WCAG), fondo,
 * tipografía, botones y disposición.
 */
const props = defineProps<{
    plantillas: {
        clave: string;
        nombre: string;
        descripcion: string;
        documento: Documento;
    }[];
    temaPorDefecto: Tema;
}>();

const editor = usarEditor();
const tema = computed(() => editor.documento.value.tema);
const avisos = computed(() => avisosContraste(tema.value));
const avisoDe = (clave: string) => {
    const a = avisos.value.find((x) => x.clave === clave);

    return a
        ? `Contraste ${a.ratio}:1 (mínimo recomendado ${a.minimo}:1)`
        : null;
};
const err = (campo: string) => editor.errores.value[`tema.${campo}`];
const off = computed(() => !editor.editable);

const COLORES: { clave: keyof ColoresTema; etiqueta: string }[] = [
    { clave: 'fondo', etiqueta: 'Fondo de la página' },
    { clave: 'texto', etiqueta: 'Texto' },
    { clave: 'titulos', etiqueta: 'Títulos' },
    { clave: 'texto_suave', etiqueta: 'Texto secundario' },
    { clave: 'boton_fondo', etiqueta: 'Fondo de botones' },
    { clave: 'boton_texto', etiqueta: 'Texto de botones' },
    { clave: 'boton_borde', etiqueta: 'Borde de botones' },
    { clave: 'boton_hover_fondo', etiqueta: 'Botón al pasar el cursor' },
    { clave: 'boton_hover_texto', etiqueta: 'Texto al pasar el cursor' },
    { clave: 'tarjeta_fondo', etiqueta: 'Fondo de tarjetas' },
    { clave: 'tarjeta_texto', etiqueta: 'Texto de tarjetas' },
    { clave: 'borde', etiqueta: 'Bordes y líneas' },
    { clave: 'enlaces', etiqueta: 'Enlaces de texto' },
    { clave: 'acento', etiqueta: 'Acento (destacados)' },
];
const OPC_FUENTES = Object.entries(FUENTES).map(([value, f]) => ({
    value,
    label: `${f.etiqueta}${f.categoria === 'serif' ? ' (con remates)' : ''}`,
}));

// ---- Plantillas ----
const plantillaElegida = ref<(typeof props.plantillas)[number] | null>(null);
const conContenido = ref(false);

const aplicarPlantilla = () => {
    const p = plantillaElegida.value;

    if (!p) {
        return;
    }

    const plantilla = JSON.parse(JSON.stringify(p.documento)) as Documento;
    editor.cambiarConDeshacer(() => {
        const doc = editor.documento.value;
        doc.tema = plantilla.tema;

        if (conContenido.value) {
            const inicio =
                doc.paginas.find((x) => x.slug === 'inicio') ?? doc.paginas[0];
            // Identificadores nuevos: nunca se mezclan con estadísticas anteriores.
            inicio.bloques = [
                ...inicio.bloques,
                ...plantilla.paginas[0].bloques.map(duplicarBloque),
            ];

            if (!doc.perfil.descripcion) {
                doc.perfil.descripcion = plantilla.perfil.descripcion;
            }
        }
    }, `Plantilla «${p.nombre}» aplicada.`);
    plantillaElegida.value = null;
};

const muestra = (p: (typeof props.plantillas)[number]) => ({
    ...variablesTema(p.documento.tema),
    ...estiloFondo(p.documento.tema),
});

// ---- Restablecer ----
const confirmarRestablecer = ref(false);
const restablecer = () => {
    editor.cambiarConDeshacer(() => {
        editor.documento.value.tema = JSON.parse(
            JSON.stringify(props.temaPorDefecto),
        ) as Tema;
    }, 'Diseño restablecido.');
    confirmarRestablecer.value = false;
};

const agregarParada = () => {
    const paradas = tema.value.fondo.paradas;

    if (paradas.length < 3) {
        paradas.splice(1, 0, {
            color: paradas[0]?.color ?? '#ffffff',
            posicion: 50,
        });
    }
};
</script>

<template>
    <div class="grid grid-cols-1 gap-6">
        <!-- Plantillas -->
        <section class="grid grid-cols-1 gap-3">
            <div>
                <h3 class="text-sm font-semibold">Plantillas</h3>
                <p class="text-xs text-muted-foreground">
                    Un punto de partida. Puedes deshacerlo.
                </p>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <button
                    v-for="p in plantillas"
                    :key="p.clave"
                    type="button"
                    class="group overflow-hidden rounded-xl border text-left transition hover:border-primary/60 focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="off"
                    @click="
                        plantillaElegida = p;
                        conContenido = false;
                    "
                >
                    <span
                        class="flex h-20 flex-col items-center justify-center gap-1.5 px-3"
                        :style="muestra(p)"
                    >
                        <span
                            class="h-1.5 w-12 rounded-full"
                            :style="{
                                background: p.documento.tema.colores.titulos,
                            }"
                        />
                        <span
                            v-for="n in 2"
                            :key="n"
                            class="h-4 w-full max-w-[7rem]"
                            :style="{
                                background:
                                    p.documento.tema.botones.estilo ===
                                    'contorno'
                                        ? 'transparent'
                                        : p.documento.tema.colores.boton_fondo,
                                opacity:
                                    p.documento.tema.botones.estilo === 'vidrio'
                                        ? 0.35
                                        : 1,
                                border: `1px solid ${p.documento.tema.colores.boton_borde}`,
                                borderRadius: `${Math.min(p.documento.tema.botones.radio, 10)}px`,
                            }"
                        />
                    </span>
                    <span class="block border-t px-2.5 py-2">
                        <span class="block text-xs font-semibold">{{
                            p.nombre
                        }}</span>
                        <span
                            class="line-clamp-2 block text-[11px] leading-snug text-muted-foreground"
                            >{{ p.descripcion }}</span
                        >
                    </span>
                </button>
            </div>
        </section>

        <!-- Colores -->
        <section class="grid grid-cols-1 gap-3">
            <h3 class="text-sm font-semibold">Colores</h3>
            <div
                v-if="avisos.length"
                class="flex gap-2 rounded-lg border border-warning/40 bg-warning-soft/50 p-3 text-xs"
                role="status"
            >
                <TriangleAlert class="mt-0.5 size-4 shrink-0 text-warning" />
                <div>
                    <p class="font-medium">
                        Algunos textos podrían leerse con dificultad:
                    </p>
                    <ul class="mt-1 list-disc pl-4">
                        <li v-for="a in avisos" :key="a.clave">
                            {{ a.descripcion }} ({{ a.ratio }}:1)
                        </li>
                    </ul>
                </div>
            </div>
            <div
                class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2"
            >
                <CampoColor
                    v-for="c in COLORES"
                    :key="c.clave"
                    v-model="tema.colores[c.clave]"
                    :etiqueta="c.etiqueta"
                    :id="`color-${c.clave}`"
                    :aviso="avisoDe(c.clave)"
                    :deshabilitado="off"
                />
            </div>
        </section>

        <!-- Fondo -->
        <section class="grid grid-cols-1 gap-3">
            <h3 class="text-sm font-semibold">Fondo</h3>
            <ControlSegmentado
                v-model="tema.fondo.tipo"
                etiqueta="Tipo de fondo"
                :deshabilitado="off"
                :opciones="[
                    { value: 'solido', label: 'Color' },
                    { value: 'degradado', label: 'Degradado' },
                    { value: 'textura', label: 'Textura' },
                    { value: 'imagen', label: 'Imagen' },
                    { value: 'video', label: 'Video' },
                ]"
            />
            <template v-if="tema.fondo.tipo === 'degradado'">
                <Deslizador
                    v-model="tema.fondo.angulo"
                    etiqueta="Ángulo"
                    id="fondo-angulo"
                    :min="0"
                    :max="360"
                    unidad="°"
                    :deshabilitado="off"
                />
                <div
                    v-for="(parada, i) in tema.fondo.paradas"
                    :key="i"
                    class="grid grid-cols-[1fr_auto] items-end gap-2"
                >
                    <div class="grid grid-cols-1 gap-2">
                        <CampoColor
                            v-model="parada.color"
                            :etiqueta="`Color ${i + 1}`"
                            :id="`parada-${i}`"
                            :deshabilitado="off"
                        />
                        <Deslizador
                            v-model="parada.posicion"
                            etiqueta="Posición"
                            :id="`parada-pos-${i}`"
                            :min="0"
                            :max="100"
                            unidad="%"
                            :deshabilitado="off"
                        />
                    </div>
                    <Button
                        v-if="tema.fondo.paradas.length > 2"
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-11"
                        aria-label="Quitar color"
                        :disabled="off"
                        @click="tema.fondo.paradas.splice(i, 1)"
                        ><X
                    /></Button>
                </div>
                <Button
                    v-if="tema.fondo.paradas.length < 3"
                    type="button"
                    variant="outline"
                    class="h-11 w-fit"
                    :disabled="off"
                    @click="agregarParada"
                    ><Plus /> Agregar un color</Button
                >
            </template>
            <template v-else-if="tema.fondo.tipo === 'textura'">
                <div
                    class="grid grid-cols-3 gap-2"
                    role="radiogroup"
                    aria-label="Textura"
                >
                    <button
                        v-for="(nombre, clave) in TEXTURAS"
                        :key="clave"
                        type="button"
                        role="radio"
                        :aria-checked="tema.fondo.textura === clave"
                        :disabled="off"
                        class="overflow-hidden rounded-lg border text-xs"
                        :class="
                            tema.fondo.textura === clave
                                ? 'ring-2 ring-primary'
                                : ''
                        "
                        @click="
                            tema.fondo.textura =
                                clave as Tema['fondo']['textura']
                        "
                    >
                        <span
                            class="block h-12"
                            :style="
                                estiloFondo({
                                    ...tema,
                                    fondo: {
                                        ...tema.fondo,
                                        tipo: 'textura',
                                        textura:
                                            clave as Tema['fondo']['textura'],
                                    },
                                })
                            "
                        />
                        <span class="block py-1">{{ nombre }}</span>
                    </button>
                </div>
                <CampoColor
                    v-model="tema.fondo.color_textura"
                    etiqueta="Color del patrón"
                    id="fondo-textura"
                    :deshabilitado="off"
                />
            </template>
            <template
                v-else-if="
                    tema.fondo.tipo === 'imagen' || tema.fondo.tipo === 'video'
                "
            >
                <SelectorMedio
                    v-if="tema.fondo.tipo === 'imagen'"
                    v-model="tema.fondo.imagen"
                    etiqueta="Imagen de fondo"
                    uso="fondo"
                    :error="err('fondo.imagen')"
                    :deshabilitado="off"
                />
                <template v-else>
                    <SelectorMedio
                        v-model="tema.fondo.video"
                        etiqueta="Video de fondo (corto, MP4 o WEBM)"
                        tipo="video"
                        :error="err('fondo.video')"
                        :deshabilitado="off"
                    />
                    <SelectorMedio
                        v-model="tema.fondo.poster"
                        etiqueta="Imagen de respaldo"
                        uso="fondo"
                        ayuda="Se muestra mientras carga, con «reducir movimiento» o con ahorro de datos."
                        :deshabilitado="off"
                    />
                    <label
                        class="flex min-h-11 items-center justify-between gap-3 text-sm"
                    >
                        <span
                            ><span class="font-medium"
                                >Ahorrar datos en celulares</span
                            ><span class="block text-xs text-muted-foreground"
                                >En pantallas pequeñas muestra solo la
                                imagen.</span
                            ></span
                        >
                        <Switch
                            v-model="tema.fondo.ahorro_datos"
                            :disabled="off"
                        />
                    </label>
                </template>
                <CampoColor
                    v-model="tema.fondo.superposicion"
                    etiqueta="Color de la capa"
                    id="fondo-capa"
                    :deshabilitado="off"
                />
                <Deslizador
                    v-model="tema.fondo.opacidad"
                    etiqueta="Intensidad de la capa"
                    id="fondo-opacidad"
                    :min="0"
                    :max="90"
                    unidad="%"
                    :deshabilitado="off"
                />
                <Deslizador
                    v-if="tema.fondo.tipo === 'imagen'"
                    v-model="tema.fondo.desenfoque"
                    etiqueta="Desenfoque"
                    id="fondo-desenfoque"
                    :min="0"
                    :max="20"
                    unidad=" px"
                    :deshabilitado="off"
                />
            </template>
        </section>

        <!-- Tipografía -->
        <section class="grid grid-cols-1 gap-3">
            <h3 class="text-sm font-semibold">Tipografía</h3>
            <div class="grid grid-cols-1 gap-1.5">
                <label
                    for="fuente-titulos"
                    class="text-xs font-medium text-muted-foreground"
                    >Títulos</label
                >
                <SelectNativo
                    id="fuente-titulos"
                    v-model="tema.tipografia.fuente_titulos"
                    :opciones="OPC_FUENTES"
                    :disabled="off"
                    class="[&_select]:h-11"
                />
            </div>
            <div class="grid grid-cols-1 gap-1.5">
                <label
                    for="fuente-texto"
                    class="text-xs font-medium text-muted-foreground"
                    >Texto</label
                >
                <SelectNativo
                    id="fuente-texto"
                    v-model="tema.tipografia.fuente_texto"
                    :opciones="OPC_FUENTES"
                    :disabled="off"
                    class="[&_select]:h-11"
                />
            </div>
            <Deslizador
                v-model="tema.tipografia.tamano"
                etiqueta="Tamaño del texto"
                id="tipo-tamano"
                :min="14"
                :max="20"
                unidad=" px"
                :deshabilitado="off"
            />
            <Deslizador
                v-model="tema.tipografia.escala_titulos"
                etiqueta="Tamaño de títulos"
                id="tipo-escala"
                :min="100"
                :max="170"
                :paso="5"
                unidad="%"
                :deshabilitado="off"
            />
            <Deslizador
                v-model="tema.tipografia.espaciado"
                etiqueta="Espaciado entre letras"
                id="tipo-espaciado"
                :min="-3"
                :max="10"
                :deshabilitado="off"
            />
            <ControlSegmentado
                v-model="tema.tipografia.peso_texto"
                etiqueta="Grosor del texto"
                :deshabilitado="off"
                :opciones="[
                    { value: 300, label: 'Ligero' },
                    { value: 400, label: 'Normal' },
                    { value: 500, label: 'Medio' },
                ]"
            />
            <ControlSegmentado
                v-model="tema.tipografia.peso_titulos"
                etiqueta="Grosor de títulos"
                :deshabilitado="off"
                :opciones="[
                    { value: 500, label: 'Medio' },
                    { value: 600, label: 'Semi' },
                    { value: 700, label: 'Negrita' },
                    { value: 800, label: 'Extra' },
                ]"
            />
            <ControlSegmentado
                v-model="tema.tipografia.alineacion"
                etiqueta="Alineación"
                :deshabilitado="off"
                :opciones="[
                    { value: 'centro', label: 'Centrada' },
                    { value: 'izquierda', label: 'A la izquierda' },
                ]"
            />
        </section>

        <!-- Botones -->
        <section class="grid grid-cols-1 gap-3">
            <h3 class="text-sm font-semibold">Botones</h3>
            <ControlSegmentado
                v-model="tema.botones.estilo"
                etiqueta="Estilo"
                :deshabilitado="off"
                :opciones="[
                    { value: 'solido', label: 'Sólido' },
                    { value: 'contorno', label: 'Contorno' },
                    { value: 'suave', label: 'Suave' },
                    { value: 'vidrio', label: 'Cristal' },
                ]"
            />
            <Deslizador
                v-model="tema.botones.radio"
                etiqueta="Redondeo de esquinas"
                id="boton-radio"
                :min="0"
                :max="40"
                unidad=" px"
                :deshabilitado="off"
            />
            <Deslizador
                v-model="tema.botones.borde"
                etiqueta="Grosor del borde"
                id="boton-borde"
                :min="0"
                :max="4"
                unidad=" px"
                :deshabilitado="off"
            />
            <ControlSegmentado
                v-model="tema.botones.sombra"
                etiqueta="Sombra"
                :deshabilitado="off"
                :opciones="[
                    { value: 'ninguna', label: 'Ninguna' },
                    { value: 'suave', label: 'Suave' },
                    { value: 'media', label: 'Media' },
                    { value: 'marcada', label: 'Sólida' },
                ]"
            />
            <ControlSegmentado
                v-model="tema.botones.tamano"
                etiqueta="Altura"
                :deshabilitado="off"
                :opciones="[
                    { value: 'compacto', label: 'Compacta' },
                    { value: 'normal', label: 'Normal' },
                    { value: 'grande', label: 'Amplia' },
                ]"
            />
            <ControlSegmentado
                v-model="tema.botones.animacion"
                etiqueta="Animación"
                :deshabilitado="off"
                :opciones="[
                    { value: 'ninguna', label: 'Ninguna' },
                    { value: 'elevar', label: 'Elevar' },
                    { value: 'pulso', label: 'Pulso' },
                    { value: 'brillo', label: 'Destello' },
                ]"
            />
            <ControlSegmentado
                v-model="tema.botones.icono"
                etiqueta="Posición del ícono"
                :deshabilitado="off"
                :opciones="[
                    { value: 'izquierda', label: 'Izquierda' },
                    { value: 'derecha', label: 'Derecha' },
                    { value: 'oculto', label: 'Sin ícono' },
                ]"
            />
            <ControlSegmentado
                v-model="tema.botones.alineacion"
                etiqueta="Texto del botón"
                :deshabilitado="off"
                :opciones="[
                    { value: 'centro', label: 'Centrado' },
                    { value: 'izquierda', label: 'A la izquierda' },
                ]"
            />
            <p class="text-xs text-muted-foreground">
                Las animaciones se desactivan si el visitante pidió «reducir
                movimiento» en su dispositivo.
            </p>
        </section>

        <!-- Disposición -->
        <section class="grid grid-cols-1 gap-3">
            <h3 class="text-sm font-semibold">Disposición</h3>
            <ControlSegmentado
                v-model="tema.disposicion.encabezado"
                etiqueta="Encabezado"
                :deshabilitado="off"
                :opciones="[
                    { value: 'centrado', label: 'Centrado' },
                    { value: 'izquierda', label: 'Foto al lado' },
                    { value: 'portada', label: 'Portada amplia' },
                ]"
            />
            <ControlSegmentado
                v-model="tema.disposicion.forma_foto"
                etiqueta="Forma de la foto"
                :deshabilitado="off"
                :opciones="[
                    { value: 'circulo', label: 'Círculo' },
                    { value: 'redondeada', label: 'Redondeada' },
                    { value: 'cuadrada', label: 'Cuadrada' },
                ]"
            />
            <ControlSegmentado
                v-model="tema.disposicion.ancho"
                etiqueta="Ancho del contenido"
                :deshabilitado="off"
                :opciones="[
                    { value: 'estrecho', label: 'Estrecho' },
                    { value: 'normal', label: 'Normal' },
                    { value: 'amplio', label: 'Amplio' },
                ]"
            />
            <ControlSegmentado
                v-model="tema.disposicion.espaciado"
                etiqueta="Espacio entre elementos"
                :deshabilitado="off"
                :opciones="[
                    { value: 'compacto', label: 'Compacto' },
                    { value: 'normal', label: 'Normal' },
                    { value: 'amplio', label: 'Amplio' },
                ]"
            />
        </section>

        <Button
            type="button"
            variant="outline"
            class="h-11 w-full"
            :disabled="off"
            @click="confirmarRestablecer = true"
        >
            <RotateCcw /> Restablecer diseño
        </Button>

        <DialogoConfirmacion
            :open="plantillaElegida !== null"
            :titulo="`¿Aplicar la plantilla «${plantillaElegida?.nombre ?? ''}»?`"
            descripcion="Se reemplazarán los colores, el fondo, la tipografía y los botones. Puedes deshacerlo."
            confirmar="Aplicar plantilla"
            @update:open="(v: boolean) => !v && (plantillaElegida = null)"
            @confirmar="aplicarPlantilla"
        >
            <label
                class="flex min-h-11 items-center justify-between gap-3 rounded-lg border p-3 text-sm"
            >
                <span
                    ><span class="font-medium"
                        >Agregar contenido de ejemplo</span
                    ><span class="block text-xs text-muted-foreground"
                        >Se suma al final de tu página de inicio; nada se
                        borra.</span
                    ></span
                >
                <Switch v-model="conContenido" />
            </label>
        </DialogoConfirmacion>

        <DialogoConfirmacion
            v-model:open="confirmarRestablecer"
            titulo="¿Restablecer el diseño?"
            descripcion="Volverán los colores, tipografía y botones originales. Tu contenido no cambia y puedes deshacerlo."
            confirmar="Restablecer"
            @confirmar="restablecer"
        />
    </div>
</template>
