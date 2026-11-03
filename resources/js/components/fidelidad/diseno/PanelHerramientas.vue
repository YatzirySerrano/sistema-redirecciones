<script setup lang="ts">
import { Gift, Plus, Trash2, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import {
    DECORACIONES,
    familia,
    fondoCss,
    FUENTES,
    ICONOS,
    PRESENTACIONES,
    SOMBRAS,
    TIPOS_FONDO,
} from '@/components/fidelidad-diseno/catalogo';
import type {
    ClaveDecoracion,
    ClaveFuente,
    ClaveIcono,
    DocumentoDiseno,
    Presentacion,
    Sombra,
    TipoFondo,
} from '@/components/fidelidad-diseno/tipos';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import CampoColor from './CampoColor.vue';
import CampoRango from './CampoRango.vue';
import type { AvisoLegibilidad } from './legibilidad';
import OpcionesVisuales from './OpcionesVisuales.vue';
import SelectorImagen from './SelectorImagen.vue';
import type { ArchivoImagen, PlantillaDiseno } from './tipos';

/**
 * Herramientas del editor por sección. Edita el documento en memoria; el
 * servidor vuelve a validar todo al guardar o publicar.
 */
const doc = defineModel<DocumentoDiseno>('documento', { required: true });

const props = defineProps<{
    seccion: string;
    archivos: ArchivoImagen[];
    plantillas: PlantillaDiseno[];
    esVariante: boolean;
    avisos: AvisoLegibilidad[];
}>();

const emit = defineEmits<{
    subida: [archivo: ArchivoImagen];
    plantilla: [plantilla: PlantillaDiseno];
}>();

const avisosSeccion = computed(() =>
    props.avisos.filter((a) => a.seccion === props.seccion),
);

const fondos = Object.entries(TIPOS_FONDO).map(([valor, nombre]) => ({
    valor: valor as TipoFondo,
    nombre,
}));
const fuentes = Object.entries(FUENTES).map(([valor, f]) => ({
    valor: valor as ClaveFuente,
    nombre: f.nombre,
    estilo: f.estilo,
}));
const decoraciones = Object.entries(DECORACIONES).map(([valor, nombre]) => ({
    valor: valor as ClaveDecoracion,
    nombre,
}));
const sombras = Object.entries(SOMBRAS).map(([valor, s]) => ({
    valor: valor as Sombra,
    nombre: s.nombre,
}));
const presentaciones = Object.entries(PRESENTACIONES).map(([valor, p]) => ({
    valor: valor as Presentacion,
    nombre: p.nombre,
    descripcion: p.descripcion,
}));
const iconos = Object.entries(ICONOS).map(([valor, i]) => ({
    valor: valor as ClaveIcono,
    nombre: i.nombre,
}));

const insertar = (
    campo: 'mensaje_progreso' | 'mensaje_meta' | 'mensaje_canje',
    marcador: string,
) => {
    const actual = doc.value.contenido[campo];

    if (actual.length + marcador.length <= 160) {
        doc.value.contenido[campo] =
            `${actual}${actual.endsWith(' ') || actual === '' ? '' : ' '}${marcador}`;
    }
};

const lineas = (texto: string) =>
    texto
        .split('\n')
        .map((l) => l.slice(0, 160))
        .slice(0, 8);
const agregarSeccion = () => {
    if (doc.value.contenido.secciones.length < 4) {
        doc.value.contenido.secciones.push({
            titulo: '¿Cómo funciona?',
            elementos: [
                'Muestra tu tarjeta o enlace al pagar.',
                'El personal confirma tu visita.',
                'Al completar la meta, ¡tu recompensa es tuya!',
            ],
        });
    }
};
</script>

<template>
    <div class="space-y-6">
        <ul
            v-if="avisosSeccion.length"
            class="space-y-1.5 rounded-lg border border-warning/30 bg-warning-soft p-3 text-xs text-warning"
            role="status"
        >
            <li v-for="(a, i) in avisosSeccion" :key="i" class="flex gap-2">
                <TriangleAlert
                    class="mt-0.5 size-3.5 shrink-0"
                    aria-hidden="true"
                />
                {{ a.mensaje }}
            </li>
        </ul>

        <!-- Plantillas -->
        <template v-if="seccion === 'plantillas'">
            <p class="text-sm text-muted-foreground">
                Elige un punto de partida y personalízalo. Se conservan tu
                logotipo, tus imágenes, tus secciones y tus términos.
            </p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <button
                    v-for="p in plantillas"
                    :key="p.clave"
                    type="button"
                    class="group overflow-hidden rounded-xl border bg-background text-left transition hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-60 aria-pressed:border-primary aria-pressed:ring-2 aria-pressed:ring-primary/30"
                    :aria-pressed="doc.plantilla === p.clave"
                    @click="emit('plantilla', p)"
                >
                    <div
                        class="relative flex h-24 flex-col justify-between p-3"
                        :style="{
                            background: fondoCss(
                                p.documento.tarjeta.fondo_tipo,
                                p.documento.tarjeta.color1,
                                p.documento.tarjeta.color2,
                                p.documento.tarjeta.angulo,
                                null,
                                {},
                            ),
                            color: p.documento.tarjeta.texto,
                        }"
                    >
                        <span
                            class="text-base leading-tight font-semibold"
                            :style="{
                                fontFamily: familia(
                                    p.documento.tipografia.titulos,
                                ),
                            }"
                            >{{ p.documento.programa.nombre }}</span
                        >
                        <span class="flex gap-1" aria-hidden="true">
                            <span
                                v-for="n in 6"
                                :key="n"
                                class="flex size-5 items-center justify-center rounded-full"
                                :style="
                                    n <= 4
                                        ? {
                                              backgroundColor:
                                                  p.documento.progreso
                                                      .completo_fondo,
                                              color: p.documento.progreso
                                                  .completo_icono,
                                          }
                                        : {
                                              border: `1.5px solid ${p.documento.progreso.pendiente_fondo}66`,
                                          }
                                "
                            >
                                <component
                                    :is="
                                        ICONOS[p.documento.progreso.icono].icono
                                    "
                                    v-if="n <= 4"
                                    class="size-3"
                                />
                            </span>
                        </span>
                    </div>
                    <div class="px-3 py-2.5">
                        <p class="text-sm font-medium">{{ p.nombre }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ p.descripcion }}
                        </p>
                    </div>
                </button>
            </div>
        </template>

        <!-- Identidad -->
        <template v-else-if="seccion === 'identidad'">
            <div class="space-y-1.5">
                <label for="d-nombre" class="text-sm font-medium"
                    >Nombre del programa</label
                >
                <Input
                    id="d-nombre"
                    v-model="doc.programa.nombre"
                    maxlength="60"
                    placeholder="Ej. Club del café"
                />
            </div>
            <div class="space-y-1.5">
                <label for="d-lema" class="text-sm font-medium"
                    >Frase de bienvenida</label
                >
                <Input
                    id="d-lema"
                    v-model="doc.programa.lema"
                    maxlength="120"
                    placeholder="Ej. Cada visita suma."
                />
            </div>
            <SelectorImagen
                v-model="doc.identidad.logo"
                etiqueta="Logotipo"
                ayuda="Cuadrado, de preferencia con fondo."
                uso="logo"
                :archivos="archivos"
                @subida="emit('subida', $event)"
            />
            <SelectorImagen
                v-if="!esVariante"
                v-model="doc.identidad.portada"
                etiqueta="Imagen principal"
                ayuda="Foto horizontal de tu local o producto."
                uso="portada"
                :archivos="archivos"
                @subida="emit('subida', $event)"
            />
            <label
                v-if="!esVariante"
                class="flex items-center justify-between gap-3 text-sm"
            >
                Mostrar el nombre del negocio
                <Switch v-model="doc.identidad.mostrar_negocio" />
            </label>
            <OpcionesVisuales
                v-if="!esVariante"
                v-model="doc.contenido.saludo"
                etiqueta="Saludo al cliente"
                :opciones="[
                    { valor: 'alias', nombre: 'Saludar por su nombre' },
                    { valor: 'oculto', nombre: 'Sin nombre' },
                ]"
            />
            <p v-if="!esVariante" class="-mt-3 text-xs text-muted-foreground">
                Se usa el alias o solo el primer nombre. Nunca se muestran
                teléfono ni correo.
            </p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-1.5">
                    <label for="d-titulos" class="text-sm font-medium"
                        >Tipografía de títulos</label
                    >
                    <select
                        id="d-titulos"
                        v-model="doc.tipografia.titulos"
                        class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
                    >
                        <option
                            v-for="f in fuentes"
                            :key="f.valor"
                            :value="f.valor"
                        >
                            {{ f.nombre }} — {{ f.estilo }}
                        </option>
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label for="d-texto" class="text-sm font-medium"
                        >Tipografía del texto</label
                    >
                    <select
                        id="d-texto"
                        v-model="doc.tipografia.texto"
                        class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
                    >
                        <option
                            v-for="f in fuentes"
                            :key="f.valor"
                            :value="f.valor"
                        >
                            {{ f.nombre }} — {{ f.estilo }}
                        </option>
                    </select>
                </div>
            </div>
        </template>

        <!-- Tarjeta -->
        <template v-else-if="seccion === 'tarjeta'">
            <OpcionesVisuales
                v-model="doc.tarjeta.orientacion"
                etiqueta="Orientación"
                :opciones="[
                    { valor: 'vertical', nombre: 'Vertical' },
                    {
                        valor: 'horizontal',
                        nombre: 'Horizontal',
                        descripcion: 'Se usa en campañas de hasta 12 visitas',
                    },
                ]"
            />
            <OpcionesVisuales
                v-model="doc.tarjeta.fondo_tipo"
                etiqueta="Fondo de la tarjeta"
                :columnas="3"
                :opciones="fondos"
            />
            <div class="space-y-3 rounded-lg border p-3">
                <CampoColor
                    v-model="doc.tarjeta.color1"
                    :etiqueta="
                        doc.tarjeta.fondo_tipo === 'solido'
                            ? 'Color de fondo'
                            : 'Color inicial'
                    "
                />
                <CampoColor
                    v-if="doc.tarjeta.fondo_tipo !== 'solido'"
                    v-model="doc.tarjeta.color2"
                    etiqueta="Color final"
                />
                <CampoRango
                    v-if="doc.tarjeta.fondo_tipo === 'degradado'"
                    v-model="doc.tarjeta.angulo"
                    etiqueta="Dirección del degradado"
                    :min="0"
                    :max="360"
                    unidad="°"
                />
                <template v-if="doc.tarjeta.fondo_tipo === 'imagen'">
                    <SelectorImagen
                        v-model="doc.tarjeta.imagen"
                        etiqueta="Imagen de fondo"
                        uso="fondo"
                        :archivos="archivos"
                        @subida="emit('subida', $event)"
                    />
                    <CampoRango
                        v-model="doc.tarjeta.oscurecer"
                        etiqueta="Oscurecer imagen"
                        :min="0"
                        :max="80"
                        unidad="%"
                    />
                </template>
            </div>
            <div class="space-y-3">
                <CampoColor v-model="doc.tarjeta.texto" etiqueta="Texto" />
                <CampoColor
                    v-model="doc.tarjeta.texto_suave"
                    etiqueta="Texto secundario"
                />
            </div>
            <CampoRango
                v-model="doc.tarjeta.radio"
                etiqueta="Esquinas redondeadas"
                :min="0"
                :max="40"
                unidad=" px"
            />
            <div class="space-y-3">
                <CampoRango
                    v-model="doc.tarjeta.borde_ancho"
                    etiqueta="Grosor del borde"
                    :min="0"
                    :max="6"
                    unidad=" px"
                />
                <CampoColor
                    v-if="doc.tarjeta.borde_ancho > 0"
                    v-model="doc.tarjeta.borde_color"
                    etiqueta="Color del borde"
                />
            </div>
            <OpcionesVisuales
                v-model="doc.tarjeta.sombra"
                etiqueta="Sombra"
                :columnas="3"
                :opciones="sombras"
            />
            <OpcionesVisuales
                v-model="doc.tarjeta.decoracion"
                etiqueta="Decoración"
                :columnas="3"
                :opciones="decoraciones"
            />
            <CampoRango
                v-if="doc.tarjeta.decoracion !== 'ninguna'"
                v-model="doc.tarjeta.decoracion_opacidad"
                etiqueta="Intensidad de la decoración"
                :min="0"
                :max="60"
                unidad="%"
            />
        </template>

        <!-- Progreso -->
        <template v-else-if="seccion === 'progreso'">
            <p class="text-sm text-muted-foreground">
                La cantidad de sellos siempre es la meta real de cada campaña;
                el diseño solo cambia cómo se ve.
            </p>
            <OpcionesVisuales
                v-model="doc.progreso.presentacion"
                etiqueta="Presentación del avance"
                :opciones="presentaciones"
            />
            <p class="-mt-3 text-xs text-muted-foreground">
                {{ PRESENTACIONES[doc.progreso.presentacion].descripcion }}
            </p>
            <OpcionesVisuales
                v-model="doc.progreso.icono"
                etiqueta="Icono de los sellos"
                :columnas="6"
                :opciones="iconos"
            >
                <template #vista="{ opcion }">
                    <component
                        :is="ICONOS[opcion.valor as ClaveIcono].icono"
                        class="size-5"
                        aria-hidden="true"
                    />
                </template>
            </OpcionesVisuales>
            <div class="space-y-3 rounded-lg border p-3">
                <p class="text-sm font-medium">Sello completado</p>
                <CampoColor
                    v-model="doc.progreso.completo_fondo"
                    etiqueta="Color del sello"
                />
                <CampoColor
                    v-model="doc.progreso.completo_icono"
                    etiqueta="Color del icono"
                />
            </div>
            <div class="space-y-3 rounded-lg border p-3">
                <p class="text-sm font-medium">Sello pendiente</p>
                <OpcionesVisuales
                    v-model="doc.progreso.pendiente_estilo"
                    etiqueta="Estilo"
                    :columnas="3"
                    :opciones="[
                        { valor: 'contorno', nombre: 'Contorno' },
                        { valor: 'tenue', nombre: 'Tenue' },
                        { valor: 'punteado', nombre: 'Punteado' },
                    ]"
                />
                <CampoColor
                    v-model="doc.progreso.pendiente_fondo"
                    etiqueta="Color del contorno"
                />
                <CampoColor
                    v-model="doc.progreso.pendiente_icono"
                    etiqueta="Color del icono"
                />
            </div>
            <label class="flex items-center justify-between gap-3 text-sm">
                Mostrar contador (6 de 10 visitas)
                <Switch v-model="doc.progreso.mostrar_contador" />
            </label>
            <label class="flex items-center justify-between gap-3 text-sm">
                Agregar barra de progreso
                <Switch v-model="doc.progreso.mostrar_barra" />
            </label>
            <div
                v-if="
                    doc.progreso.mostrar_barra ||
                    doc.progreso.presentacion === 'barra'
                "
                class="space-y-3"
            >
                <CampoColor
                    v-model="doc.progreso.barra_color"
                    etiqueta="Color de la barra"
                />
                <CampoColor
                    v-model="doc.progreso.barra_fondo"
                    etiqueta="Color del riel"
                />
            </div>
        </template>

        <!-- Mensajes -->
        <template v-else-if="seccion === 'mensajes'">
            <label class="flex items-center justify-between gap-3 text-sm">
                Mostrar nombre de la campaña
                <Switch v-model="doc.contenido.mostrar_campana" />
            </label>
            <label class="flex items-center justify-between gap-3 text-sm">
                Mostrar la recompensa en la tarjeta
                <Switch v-model="doc.contenido.mostrar_recompensa" />
            </label>
            <div
                v-for="campo in [
                    {
                        clave: 'mensaje_progreso' as const,
                        etiqueta: 'Mientras acumula visitas',
                        marcadores: [
                            '{faltan}',
                            '{meta}',
                            '{visitas}',
                            '{recompensa}',
                        ],
                    },
                    {
                        clave: 'mensaje_meta' as const,
                        etiqueta: 'Al completar la meta',
                        marcadores: ['{recompensa}'],
                    },
                    {
                        clave: 'mensaje_canje' as const,
                        etiqueta: 'Después de canjear la recompensa',
                        marcadores: ['{faltan}', '{meta}'],
                    },
                ]"
                :key="campo.clave"
                class="space-y-1.5"
            >
                <label :for="`d-${campo.clave}`" class="text-sm font-medium">{{
                    campo.etiqueta
                }}</label>
                <Textarea
                    :id="`d-${campo.clave}`"
                    v-model="doc.contenido[campo.clave]"
                    maxlength="160"
                    rows="2"
                />
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-xs text-muted-foreground">Insertar:</span>
                    <button
                        v-for="m in campo.marcadores"
                        :key="m"
                        type="button"
                        class="rounded-md border px-1.5 py-0.5 font-mono text-[0.7rem] hover:bg-muted disabled:opacity-50"
                        @click="insertar(campo.clave, m)"
                    >
                        {{ m }}
                    </button>
                    <span
                        class="ml-auto text-xs text-muted-foreground tabular-nums"
                        >{{ doc.contenido[campo.clave].length }}/160</span
                    >
                </div>
            </div>
            <p class="flex items-start gap-2 text-xs text-muted-foreground">
                <Gift class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                La recompensa y la meta se toman de la campaña. Para cambiarlas,
                edita la campaña.
            </p>
        </template>

        <!-- Página -->
        <template v-else-if="seccion === 'pagina'">
            <OpcionesVisuales
                v-model="doc.pagina.fondo_tipo"
                etiqueta="Fondo de la página"
                :columnas="3"
                :opciones="fondos"
            />
            <div class="space-y-3 rounded-lg border p-3">
                <CampoColor
                    v-model="doc.pagina.color1"
                    :etiqueta="
                        doc.pagina.fondo_tipo === 'solido'
                            ? 'Color de fondo'
                            : 'Color inicial'
                    "
                />
                <CampoColor
                    v-if="doc.pagina.fondo_tipo !== 'solido'"
                    v-model="doc.pagina.color2"
                    etiqueta="Color final"
                />
                <CampoRango
                    v-if="doc.pagina.fondo_tipo === 'degradado'"
                    v-model="doc.pagina.angulo"
                    etiqueta="Dirección del degradado"
                    :min="0"
                    :max="360"
                    unidad="°"
                />
                <SelectorImagen
                    v-if="doc.pagina.fondo_tipo === 'imagen'"
                    v-model="doc.pagina.imagen"
                    etiqueta="Imagen de fondo"
                    uso="fondo"
                    :archivos="archivos"
                    @subida="emit('subida', $event)"
                />
            </div>
            <OpcionesVisuales
                v-model="doc.pagina.decoracion"
                etiqueta="Decoración del fondo"
                :columnas="3"
                :opciones="decoraciones"
            />
            <div class="space-y-3">
                <CampoColor
                    v-model="doc.colores.primario"
                    etiqueta="Color principal"
                />
                <CampoColor
                    v-model="doc.colores.secundario"
                    etiqueta="Color secundario"
                />
                <CampoColor v-model="doc.colores.texto" etiqueta="Texto" />
                <CampoColor
                    v-model="doc.colores.texto_suave"
                    etiqueta="Texto secundario"
                />
                <CampoColor
                    v-model="doc.colores.superficie"
                    etiqueta="Recuadros informativos"
                />
                <CampoColor v-model="doc.colores.borde" etiqueta="Bordes" />
            </div>

            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium">Secciones informativas</p>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        :disabled="doc.contenido.secciones.length >= 4"
                        @click="agregarSeccion"
                    >
                        <Plus /> Agregar
                    </Button>
                </div>
                <div
                    v-for="(s, i) in doc.contenido.secciones"
                    :key="i"
                    class="space-y-2 rounded-lg border p-3"
                >
                    <div class="flex gap-2">
                        <Input
                            v-model="s.titulo"
                            maxlength="60"
                            :aria-label="`Título de la sección ${i + 1}`"
                            placeholder="Título"
                        />
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            :aria-label="`Quitar la sección ${i + 1}`"
                            @click="doc.contenido.secciones.splice(i, 1)"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                    <Textarea
                        :model-value="s.elementos.join('\n')"
                        rows="3"
                        :aria-label="`Puntos de la sección ${i + 1}, uno por línea`"
                        placeholder="Un punto por línea (máximo 8)"
                        @update:model-value="
                            s.elementos = lineas(String($event))
                        "
                    />
                </div>
                <p
                    v-if="doc.contenido.secciones.length === 0"
                    class="text-xs text-muted-foreground"
                >
                    Ej. «¿Cómo funciona?», horarios o beneficios del programa.
                </p>
            </div>

            <div class="space-y-1.5">
                <label for="d-terminos" class="text-sm font-medium"
                    >Términos y condiciones</label
                >
                <Textarea
                    id="d-terminos"
                    v-model="doc.contenido.terminos"
                    maxlength="2000"
                    rows="5"
                    placeholder="Ej. Una visita por día. Recompensa no canjeable por efectivo."
                />
                <p
                    class="text-right text-xs text-muted-foreground tabular-nums"
                >
                    {{ doc.contenido.terminos.length }}/2000
                </p>
            </div>
        </template>
    </div>
</template>
