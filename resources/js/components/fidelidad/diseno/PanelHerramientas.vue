<script setup lang="ts">
import { Gift, Plus, Trash2, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import Ayuda from '@/components/comun/Ayuda.vue';
import {
    familia,
    fondoCss,
    FUENTES,
    PRESENTACIONES,
    SOMBRAS,
    TIPOS_FONDO,
} from '@/components/fidelidad-diseno/catalogo';
import type {
    ClaveDecoracion,
    ClaveFuente,
    DocumentoDiseno,
    Presentacion,
    Sombra,
    TipoFondo,
} from '@/components/fidelidad-diseno/tipos';
import IconoCatalogo from '@/components/iconos/IconoCatalogo.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import CampoColor from './CampoColor.vue';
import CampoRango from './CampoRango.vue';
import type { AvisoLegibilidad } from './legibilidad';
import OpcionesVisuales from './OpcionesVisuales.vue';
import type { ClaveHerramienta } from './pasos';
import SelectorDecoracion from './SelectorDecoracion.vue';
import SelectorIconoSello from './SelectorIconoSello.vue';
import SelectorImagen from './SelectorImagen.vue';
import type { ArchivoImagen, PlantillaDiseno } from './tipos';

/**
 * Propiedades de la herramienta elegida en el editor «Diseñar mi
 * programa». Edita el documento en memoria; el servidor vuelve a validar
 * todo al guardar o publicar. Nada aquí cambia visitas, metas ni
 * recompensas: esas vienen de las campañas.
 */
const doc = defineModel<DocumentoDiseno>('documento', { required: true });

const props = defineProps<{
    herramienta: ClaveHerramienta;
    archivos: ArchivoImagen[];
    plantillas: PlantillaDiseno[];
    esVariante: boolean;
    avisos: AvisoLegibilidad[];
    urlSubida: string;
    medios: Record<string, string>;
    /** Fondo de la página cuando se edita una variante (viene del general). */
    general: DocumentoDiseno | null;
}>();

const emit = defineEmits<{
    subida: [archivo: ArchivoImagen];
    plantilla: [plantilla: PlantillaDiseno];
    previsualizar: [
        cambio: {
            ruta: 'pagina.decoracion' | 'tarjeta.decoracion';
            valor: ClaveDecoracion;
        } | null,
    ];
}>();

const avisosHerramienta = computed(() =>
    props.avisos.filter((a) => a.herramienta === props.herramienta),
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
const sombras = Object.entries(SOMBRAS).map(([valor, s]) => ({
    valor: valor as Sombra,
    nombre: s.nombre,
}));
const presentaciones = Object.entries(PRESENTACIONES).map(([valor, p]) => ({
    valor: valor as Presentacion,
    nombre: p.nombre,
    descripcion: p.descripcion,
}));

const pagina = computed(() => props.general ?? doc.value);
const fondoPagina = computed(() =>
    fondoCss(
        pagina.value.pagina.fondo_tipo,
        pagina.value.pagina.color1,
        pagina.value.pagina.color2,
        pagina.value.pagina.angulo,
        pagina.value.pagina.imagen,
        props.medios,
        20,
    ),
);
const fondoTarjeta = computed(() =>
    fondoCss(
        doc.value.tarjeta.fondo_tipo,
        doc.value.tarjeta.color1,
        doc.value.tarjeta.color2,
        doc.value.tarjeta.angulo,
        doc.value.tarjeta.imagen,
        props.medios,
        doc.value.tarjeta.oscurecer,
    ),
);

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

const mensajes = [
    {
        clave: 'mensaje_progreso' as const,
        etiqueta: 'Mientras acumula visitas',
        marcadores: ['{faltan}', '{meta}', '{visitas}', '{recompensa}'],
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
];
</script>

<template>
    <div class="space-y-6">
        <ul
            v-if="avisosHerramienta.length"
            class="space-y-1.5 rounded-lg border border-warning/30 bg-warning-soft p-3 text-xs text-warning"
            role="status"
        >
            <li v-for="(a, i) in avisosHerramienta" :key="i" class="flex gap-2">
                <TriangleAlert
                    class="mt-0.5 size-3.5 shrink-0"
                    aria-hidden="true"
                />
                {{ a.mensaje }}
            </li>
        </ul>

        <!-- 1. Plantillas -->
        <template v-if="herramienta === 'plantillas'">
            <p class="text-sm text-muted-foreground">
                Elige un punto de partida y personalízalo en los siguientes
                pasos. Se conservan tu logotipo, tus imágenes, tu información y
                tus términos.
            </p>
            <div
                class="grid grid-cols-[repeat(auto-fill,minmax(13rem,1fr))] gap-3"
            >
                <button
                    v-for="p in plantillas"
                    :key="p.clave"
                    type="button"
                    class="group relative overflow-hidden rounded-xl border-2 bg-background text-left transition hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-60 aria-pressed:border-primary aria-pressed:shadow-[0_0_0_3px] aria-pressed:shadow-primary/20"
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
                                <IconoCatalogo
                                    v-if="n <= 4"
                                    :valor="p.documento.progreso.icono"
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
                    <span
                        v-if="doc.plantilla === p.clave"
                        class="absolute top-2 right-2 rounded-full bg-primary px-2 py-0.5 text-[0.68rem] font-semibold text-primary-foreground"
                        >En uso</span
                    >
                </button>
            </div>
        </template>

        <!-- 2. Logotipo y nombre -->
        <template v-else-if="herramienta === 'encabezado'">
            <div class="space-y-1.5">
                <label for="d-nombre" class="text-sm font-medium"
                    >Nombre del programa</label
                >
                <Input
                    id="d-nombre"
                    v-model="doc.programa.nombre"
                    class="h-10"
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
                    class="h-10"
                    maxlength="120"
                    placeholder="Ej. Cada visita suma."
                />
            </div>
            <SelectorImagen
                v-model="doc.identidad.logo"
                etiqueta="Logotipo"
                ayuda="Cuadrado, de preferencia con fondo."
                uso="logo"
                :url="urlSubida"
                :archivos="archivos"
                @subida="emit('subida', $event)"
            />
            <template v-if="!esVariante">
                <SelectorImagen
                    v-model="doc.identidad.portada"
                    etiqueta="Imagen principal"
                    ayuda="Foto horizontal de tu local o producto, arriba de la página."
                    uso="portada"
                    :url="urlSubida"
                    :archivos="archivos"
                    @subida="emit('subida', $event)"
                />
                <label
                    class="flex min-h-10 items-center justify-between gap-3 text-sm"
                >
                    Mostrar el nombre del negocio
                    <Switch v-model="doc.identidad.mostrar_negocio" />
                </label>
                <div class="space-y-1.5">
                    <OpcionesVisuales
                        v-model="doc.contenido.saludo"
                        etiqueta="Saludo al cliente"
                        :opciones="[
                            { valor: 'alias', nombre: 'Saludar por su nombre' },
                            { valor: 'oculto', nombre: 'Sin nombre' },
                        ]"
                    />
                    <p class="text-xs text-muted-foreground">
                        Se usa el alias o solo el primer nombre. Nunca se
                        muestran teléfono ni correo.
                    </p>
                </div>
            </template>
        </template>

        <!-- 2. Colores de la página -->
        <template v-else-if="herramienta === 'colores'">
            <p class="text-sm text-muted-foreground">
                Colores de los textos y recuadros de la página. Los de la
                tarjeta están en «Tarjeta».
            </p>
            <div class="space-y-3">
                <CampoColor
                    v-model="doc.colores.primario"
                    etiqueta="Color principal"
                    ayuda="Viñetas y detalles"
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
        </template>

        <!-- 2. Fondo de la página -->
        <template v-else-if="herramienta === 'fondo'">
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
                    :url="urlSubida"
                    :archivos="archivos"
                    @subida="emit('subida', $event)"
                />
            </div>
        </template>

        <!-- 2. Tarjeta -->
        <template v-else-if="herramienta === 'tarjeta'">
            <p class="text-sm text-muted-foreground">
                La tarjeta aparece dentro de la página, con el avance de cada
                cliente.
            </p>
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
                        :url="urlSubida"
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
                <CampoColor
                    v-model="doc.tarjeta.texto"
                    etiqueta="Texto de la tarjeta"
                />
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
        </template>

        <!-- 2. Tipografía -->
        <template v-else-if="herramienta === 'tipografia'">
            <div
                v-for="campo in [
                    {
                        clave: 'titulos' as const,
                        etiqueta: 'Letra de los títulos',
                    },
                    { clave: 'texto' as const, etiqueta: 'Letra del texto' },
                ]"
                :key="campo.clave"
                class="space-y-1.5"
            >
                <label :for="`d-${campo.clave}`" class="text-sm font-medium">{{
                    campo.etiqueta
                }}</label>
                <select
                    :id="`d-${campo.clave}`"
                    v-model="doc.tipografia[campo.clave]"
                    class="h-10 w-full rounded-md border border-input bg-background px-2 text-sm"
                >
                    <option
                        v-for="f in fuentes"
                        :key="f.valor"
                        :value="f.valor"
                    >
                        {{ f.nombre }} — {{ f.estilo }}
                    </option>
                </select>
                <p
                    class="truncate text-lg"
                    :style="{
                        fontFamily: familia(doc.tipografia[campo.clave]),
                    }"
                    aria-hidden="true"
                >
                    {{ doc.programa.nombre || 'Programa de recompensas' }}
                </p>
            </div>
        </template>

        <!-- 2. Decoración -->
        <template v-else-if="herramienta === 'decoracion'">
            <p class="text-sm text-muted-foreground">
                Pasa el cursor (o enfoca con el teclado) sobre una opción para
                verla en tu página. Toca o haz clic para elegirla.
            </p>
            <SelectorDecoracion
                v-if="!esVariante"
                v-model="doc.pagina.decoracion"
                etiqueta="Decoración del fondo de la página"
                :fondo="fondoPagina"
                :color="doc.colores.primario"
                :opacidad="10"
                @previsualizar="
                    emit(
                        'previsualizar',
                        $event
                            ? { ruta: 'pagina.decoracion', valor: $event }
                            : null,
                    )
                "
            />
            <SelectorDecoracion
                v-model="doc.tarjeta.decoracion"
                etiqueta="Decoración de la tarjeta"
                :fondo="fondoTarjeta"
                :color="doc.tarjeta.texto"
                :opacidad="doc.tarjeta.decoracion_opacidad"
                @previsualizar="
                    emit(
                        'previsualizar',
                        $event
                            ? { ruta: 'tarjeta.decoracion', valor: $event }
                            : null,
                    )
                "
            />
            <CampoRango
                v-if="doc.tarjeta.decoracion !== 'ninguna'"
                v-model="doc.tarjeta.decoracion_opacidad"
                etiqueta="Intensidad en la tarjeta"
                :min="0"
                :max="60"
                unidad="%"
            />
        </template>

        <!-- 3. Avance de visitas -->
        <template v-else-if="herramienta === 'progreso'">
            <p class="text-sm text-muted-foreground">
                La cantidad de sellos siempre es la meta real de cada campaña;
                aquí solo eliges cómo se ve.
            </p>
            <div class="space-y-1.5">
                <OpcionesVisuales
                    v-model="doc.progreso.presentacion"
                    etiqueta="Forma del avance"
                    :opciones="presentaciones"
                />
                <p class="text-xs text-muted-foreground">
                    {{ PRESENTACIONES[doc.progreso.presentacion].descripcion }}
                </p>
            </div>
            <SelectorIconoSello
                v-model="doc.progreso.icono"
                etiqueta="Ícono de los sellos"
            />
            <div class="space-y-3 rounded-lg border p-3">
                <p class="text-sm font-medium">Sello completado</p>
                <CampoColor
                    v-model="doc.progreso.completo_fondo"
                    etiqueta="Color del sello"
                />
                <CampoColor
                    v-model="doc.progreso.completo_icono"
                    etiqueta="Color del ícono"
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
                    etiqueta="Color del ícono"
                />
            </div>
            <label
                class="flex min-h-10 items-center justify-between gap-3 text-sm"
            >
                Mostrar contador (6 de 10 visitas)
                <Switch v-model="doc.progreso.mostrar_contador" />
            </label>
            <label
                class="flex min-h-10 items-center justify-between gap-3 text-sm"
            >
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

        <!-- 3. Recompensa y mensajes -->
        <template v-else-if="herramienta === 'mensajes'">
            <p
                class="flex items-start gap-2 rounded-lg border bg-muted/40 px-3 py-2 text-xs text-muted-foreground"
            >
                <Gift class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                <span>
                    La meta y la recompensa se toman de la campaña.
                    <Ayuda
                        texto="La campaña define cuántas visitas necesita un cliente y qué recompensa recibirá. Para cambiarlas, edita la campaña en «Programas › Campañas»."
                        etiqueta="Campaña"
                    />
                </span>
            </p>
            <label
                class="flex min-h-10 items-center justify-between gap-3 text-sm"
            >
                Mostrar nombre de la campaña
                <Switch v-model="doc.contenido.mostrar_campana" />
            </label>
            <label
                class="flex min-h-10 items-center justify-between gap-3 text-sm"
            >
                Mostrar la recompensa en la tarjeta
                <Switch v-model="doc.contenido.mostrar_recompensa" />
            </label>
            <div
                v-for="campo in mensajes"
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
                        class="inline-flex h-8 items-center rounded-md border px-2 font-mono text-[0.7rem] hover:bg-muted disabled:opacity-50"
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
        </template>

        <!-- 3. Información y condiciones -->
        <template v-else-if="herramienta === 'informacion'">
            <div class="space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm font-medium">Información del programa</p>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-10"
                        :disabled="doc.contenido.secciones.length >= 4"
                        @click="agregarSeccion"
                    >
                        <Plus /> Agregar sección
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
                            class="h-10"
                            maxlength="60"
                            :aria-label="`Título de la sección ${i + 1}`"
                            placeholder="Título"
                        />
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            class="size-10"
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
                    class="rounded-lg border border-dashed px-3 py-4 text-center text-xs text-muted-foreground"
                >
                    Opcional. Ej. «¿Cómo funciona?», horarios o beneficios del
                    programa.
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
