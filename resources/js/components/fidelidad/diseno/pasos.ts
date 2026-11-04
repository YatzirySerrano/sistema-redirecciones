/**
 * Pasos del editor «Diseñar mi programa» y las herramientas de cada uno.
 *
 * Todo se organiza alrededor de la ÚNICA página que abre el consumidor (su
 * tarjeta, su progreso y la información del programa). `rutas` indica qué
 * partes del documento cambia cada herramienta: sirve para el resumen de
 * cambios del paso 4. `soloGeneral` marca lo que pertenece a la página (se
 * toma del diseño general y no existe en una variante de campaña).
 */
import type { LucideIcon } from '@lucide/vue';
import {
    CreditCard,
    Image,
    Info,
    LayoutTemplate,
    MessageSquareText,
    PaintBucket,
    Palette,
    Send,
    Sparkles,
    Stamp,
    Type,
} from '@lucide/vue';
import type { ClaveZona } from '@/components/fidelidad-diseno/edicion';

export type ClaveHerramienta =
    | 'plantillas'
    | 'encabezado'
    | 'colores'
    | 'fondo'
    | 'tarjeta'
    | 'tipografia'
    | 'decoracion'
    | 'progreso'
    | 'mensajes'
    | 'informacion'
    | 'revisar';

export type Herramienta = {
    clave: ClaveHerramienta;
    nombre: string;
    descripcion: string;
    icono: LucideIcon;
    rutas: string[];
    soloGeneral?: boolean;
};

export type Paso = {
    numero: number;
    clave: 'plantilla' | 'apariencia' | 'visitas' | 'publicar';
    nombre: string;
    corto: string;
    descripcion: string;
    herramientas: Herramienta[];
};

export const PASOS: Paso[] = [
    {
        numero: 1,
        clave: 'plantilla',
        nombre: 'Elegir plantilla',
        corto: 'Plantilla',
        descripcion:
            'Un punto de partida con colores, letras y sellos que combinan. Después puedes cambiar todo.',
        herramientas: [
            {
                clave: 'plantillas',
                nombre: 'Plantillas',
                descripcion: 'Elige un estilo inicial',
                icono: LayoutTemplate,
                rutas: ['plantilla'],
            },
        ],
    },
    {
        numero: 2,
        clave: 'apariencia',
        nombre: 'Identidad y apariencia',
        corto: 'Apariencia',
        descripcion:
            'Tu logotipo, colores, fondo, letras y decoración de la página y de la tarjeta.',
        herramientas: [
            {
                clave: 'encabezado',
                nombre: 'Logotipo y nombre',
                descripcion: 'Logotipo, imagen principal y bienvenida',
                icono: Image,
                rutas: ['programa', 'identidad', 'contenido.saludo'],
            },
            {
                clave: 'colores',
                nombre: 'Colores',
                descripcion: 'Colores de textos y recuadros de la página',
                icono: Palette,
                rutas: ['colores'],
                soloGeneral: true,
            },
            {
                clave: 'fondo',
                nombre: 'Fondo de la página',
                descripcion: 'Color, degradado o imagen detrás de todo',
                icono: PaintBucket,
                rutas: [
                    'pagina.fondo_tipo',
                    'pagina.color1',
                    'pagina.color2',
                    'pagina.angulo',
                    'pagina.imagen',
                ],
                soloGeneral: true,
            },
            {
                clave: 'tarjeta',
                nombre: 'Tarjeta',
                descripcion: 'Fondo, textos, esquinas, borde y sombra',
                icono: CreditCard,
                rutas: [
                    'tarjeta.orientacion',
                    'tarjeta.fondo_tipo',
                    'tarjeta.color1',
                    'tarjeta.color2',
                    'tarjeta.angulo',
                    'tarjeta.imagen',
                    'tarjeta.oscurecer',
                    'tarjeta.texto',
                    'tarjeta.texto_suave',
                    'tarjeta.radio',
                    'tarjeta.borde_ancho',
                    'tarjeta.borde_color',
                    'tarjeta.sombra',
                ],
            },
            {
                clave: 'tipografia',
                nombre: 'Tipografía',
                descripcion: 'Letras de títulos y textos',
                icono: Type,
                rutas: ['tipografia'],
            },
            {
                clave: 'decoracion',
                nombre: 'Decoración',
                descripcion: 'Patrones sobre el fondo y la tarjeta',
                icono: Sparkles,
                rutas: [
                    'pagina.decoracion',
                    'tarjeta.decoracion',
                    'tarjeta.decoracion_opacidad',
                ],
            },
        ],
    },
    {
        numero: 3,
        clave: 'visitas',
        nombre: 'Visitas y recompensas',
        corto: 'Visitas',
        descripcion:
            'Cómo se ve el avance, qué mensajes recibe tu cliente y la información del programa.',
        herramientas: [
            {
                clave: 'progreso',
                nombre: 'Avance de visitas',
                descripcion: 'Forma, ícono y colores de los sellos',
                icono: Stamp,
                rutas: ['progreso'],
            },
            {
                clave: 'mensajes',
                nombre: 'Recompensa y mensajes',
                descripcion: 'Textos según el avance del cliente',
                icono: MessageSquareText,
                rutas: [
                    'contenido.mostrar_campana',
                    'contenido.mostrar_recompensa',
                    'contenido.mensaje_progreso',
                    'contenido.mensaje_meta',
                    'contenido.mensaje_canje',
                ],
            },
            {
                clave: 'informacion',
                nombre: 'Información y condiciones',
                descripcion: 'Cómo funciona, horarios y términos',
                icono: Info,
                rutas: ['contenido.secciones', 'contenido.terminos'],
                soloGeneral: true,
            },
        ],
    },
    {
        numero: 4,
        clave: 'publicar',
        nombre: 'Revisar y publicar',
        corto: 'Publicar',
        descripcion:
            'Revisa tus cambios y los avisos de lectura antes de que tus clientes los vean.',
        herramientas: [
            {
                clave: 'revisar',
                nombre: 'Revisar y publicar',
                descripcion: 'Resumen, avisos, versiones anteriores',
                icono: Send,
                rutas: [],
            },
        ],
    },
];

/** Herramientas del paso según el tipo de diseño que se edita. */
export function herramientasDe(paso: Paso, esVariante: boolean): Herramienta[] {
    return paso.herramientas.filter((h) => !(esVariante && h.soloGeneral));
}

export function pasoDe(herramienta: ClaveHerramienta): Paso {
    return (
        PASOS.find((p) =>
            p.herramientas.some((h) => h.clave === herramienta),
        ) ?? PASOS[0]
    );
}

export function herramienta(clave: ClaveHerramienta): Herramienta {
    return PASOS.flatMap((p) => p.herramientas).find((h) => h.clave === clave)!;
}

/** Qué herramienta abre cada zona tocada en la vista previa. */
export const ZONAS: Record<
    ClaveZona,
    { nombre: string; herramienta: ClaveHerramienta; soloGeneral?: boolean }
> = {
    fondo: {
        nombre: 'Fondo de la página',
        herramienta: 'fondo',
        soloGeneral: true,
    },
    portada: {
        nombre: 'Imagen principal',
        herramienta: 'encabezado',
        soloGeneral: true,
    },
    logo: { nombre: 'Logotipo', herramienta: 'encabezado' },
    encabezado: { nombre: 'Nombre y bienvenida', herramienta: 'encabezado' },
    tarjeta: { nombre: 'Tarjeta', herramienta: 'tarjeta' },
    progreso: { nombre: 'Avance de visitas', herramienta: 'progreso' },
    mensaje: { nombre: 'Mensaje de la recompensa', herramienta: 'mensajes' },
    secciones: {
        nombre: 'Información del programa',
        herramienta: 'informacion',
        soloGeneral: true,
    },
    terminos: {
        nombre: 'Términos y condiciones',
        herramienta: 'informacion',
        soloGeneral: true,
    },
};

/** Herramientas cuyo contenido cambió entre dos documentos. */
export function herramientasCambiadas(
    actual: unknown,
    referencia: unknown,
    esVariante: boolean,
): Herramienta[] {
    const valor = (d: unknown, ruta: string) =>
        JSON.stringify(
            ruta
                .split('.')
                .reduce<unknown>(
                    (n, k) =>
                        n && typeof n === 'object'
                            ? (n as Record<string, unknown>)[k]
                            : undefined,
                    d,
                ),
        );

    return PASOS.flatMap((p) => herramientasDe(p, esVariante)).filter((h) =>
        h.rutas.some((r) => valor(actual, r) !== valor(referencia, r)),
    );
}
