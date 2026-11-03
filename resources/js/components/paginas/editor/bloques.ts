import {
    AtSign,
    CircleUserRound,
    Contact,
    FileStack,
    GalleryHorizontal,
    Heading,
    Image,
    Images,
    Link,
    Mail,
    MapPin,
    MessageCircle,
    Minus,
    Music,
    Phone,
    QrCode,
    SquareStack,
    Text,
    Video,
} from '@lucide/vue';
import type { Component } from 'vue';
import type { Bloque, TipoBloque } from '../tipos';

export const uuid = (): string =>
    typeof crypto !== 'undefined' && 'randomUUID' in crypto
        ? crypto.randomUUID()
        : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
              const r = (Math.random() * 16) | 0;

              return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
          });

export type Categoria =
    | 'Botones y contacto'
    | 'Multimedia'
    | 'Estructura'
    | 'Interacción';

export const TIPOS: Record<
    TipoBloque,
    {
        etiqueta: string;
        descripcion: string;
        icono: Component;
        categoria: Categoria;
    }
> = {
    enlace: {
        etiqueta: 'Enlace',
        descripcion: 'Botón a cualquier sitio web',
        icono: Link,
        categoria: 'Botones y contacto',
    },
    redes: {
        etiqueta: 'Redes sociales',
        descripcion: 'Íconos de tus perfiles',
        icono: AtSign,
        categoria: 'Botones y contacto',
    },
    whatsapp: {
        etiqueta: 'WhatsApp',
        descripcion: 'Abre un chat con mensaje listo',
        icono: MessageCircle,
        categoria: 'Botones y contacto',
    },
    telefono: {
        etiqueta: 'Llamada',
        descripcion: 'Botón para llamar',
        icono: Phone,
        categoria: 'Botones y contacto',
    },
    correo: {
        etiqueta: 'Correo',
        descripcion: 'Botón para escribir un correo',
        icono: Mail,
        categoria: 'Botones y contacto',
    },
    mapa: {
        etiqueta: 'Ubicación',
        descripcion: 'Dirección, mapa y cómo llegar',
        icono: MapPin,
        categoria: 'Botones y contacto',
    },
    subpagina: {
        etiqueta: 'Ir a otra página',
        descripcion: 'Enlace a una de tus páginas',
        icono: FileStack,
        categoria: 'Botones y contacto',
    },
    imagen: {
        etiqueta: 'Imagen',
        descripcion: 'Foto o banner, con enlace opcional',
        icono: Image,
        categoria: 'Multimedia',
    },
    carrusel: {
        etiqueta: 'Carrusel',
        descripcion: 'Galería deslizable de imágenes',
        icono: GalleryHorizontal,
        categoria: 'Multimedia',
    },
    video: {
        etiqueta: 'Video',
        descripcion: 'YouTube, Vimeo o tu propio video',
        icono: Video,
        categoria: 'Multimedia',
    },
    musica: {
        etiqueta: 'Música',
        descripcion: 'Spotify, SoundCloud o Apple Music',
        icono: Music,
        categoria: 'Multimedia',
    },
    titulo: {
        etiqueta: 'Título',
        descripcion: 'Encabezado de sección',
        icono: Heading,
        categoria: 'Estructura',
    },
    texto: {
        etiqueta: 'Texto',
        descripcion: 'Párrafo con negritas y cursivas',
        icono: Text,
        categoria: 'Estructura',
    },
    separador: {
        etiqueta: 'Separador',
        descripcion: 'Línea o espacio entre secciones',
        icono: Minus,
        categoria: 'Estructura',
    },
    grupo: {
        etiqueta: 'Sección',
        descripcion: 'Agrupa botones en lista o cuadrícula',
        icono: SquareStack,
        categoria: 'Estructura',
    },
    tarjeta: {
        etiqueta: 'Tarjeta informativa',
        descripcion: 'Imagen, texto y botón',
        icono: CircleUserRound,
        categoria: 'Estructura',
    },
    contacto: {
        etiqueta: 'Formulario de contacto',
        descripcion: 'Recibe mensajes en tu panel',
        icono: Contact,
        categoria: 'Interacción',
    },
    qr: {
        etiqueta: 'Código QR',
        descripcion: 'QR de esta página para compartir',
        icono: QrCode,
        categoria: 'Interacción',
    },
};

export const ICONO_IMAGENES = Images;

/** Elemento nuevo con valores iniciales útiles. */
export function nuevoBloque(tipo: TipoBloque): Bloque {
    const base: Bloque = {
        id: uuid(),
        tipo,
        oculto: false,
        visible_desde: null,
        visible_hasta: null,
    };
    const boton = {
        subtitulo: '',
        icono: null,
        miniatura: null,
        destacado: false,
        insignia: '',
        animacion: 'tema' as const,
        tamano: 'normal',
        color_fondo: null,
        color_texto: null,
    };

    switch (tipo) {
        case 'enlace':
            return {
                ...base,
                ...boton,
                titulo: 'Nuevo enlace',
                icono: 'enlace',
                url: '',
            };
        case 'whatsapp':
            return {
                ...base,
                ...boton,
                titulo: 'Escríbenos por WhatsApp',
                icono: 'red:whatsapp',
                telefono: '',
                mensaje: 'Hola, vi su página y quiero más información.',
            };
        case 'telefono':
            return {
                ...base,
                ...boton,
                titulo: 'Llámanos',
                icono: 'telefono',
                telefono: '',
            };
        case 'correo':
            return {
                ...base,
                ...boton,
                titulo: 'Envíanos un correo',
                icono: 'correo',
                email: '',
                asunto: '',
            };
        case 'subpagina':
            return {
                ...base,
                ...boton,
                titulo: 'Ver más',
                icono: 'flecha',
                pagina_id: '',
            };
        case 'redes':
            return {
                ...base,
                estilo: 'iconos',
                tamano: 'normal',
                elementos: [
                    {
                        id: uuid(),
                        red: 'instagram',
                        valor: '',
                        alt: '',
                        archivo: null,
                    },
                ],
            };
        case 'mapa':
            return {
                ...base,
                titulo: 'Visítanos',
                direccion: '',
                mostrar_mapa: true,
                boton: 'Cómo llegar',
            };
        case 'video':
            return {
                ...base,
                titulo: '',
                fuente: 'externo',
                url: '',
                proveedor: '',
                ref: '',
                archivo: null,
                poster: null,
            };
        case 'musica':
            return { ...base, titulo: '', url: '', proveedor: '', ref: '' };
        case 'imagen':
            return {
                ...base,
                archivo: null,
                alt: '',
                url: '',
                proporcion: 'original',
                redondeo: true,
            };
        case 'carrusel':
            return {
                ...base,
                titulo: '',
                proporcion: 'cuadrada',
                elementos: [],
            };
        case 'titulo':
            return {
                ...base,
                texto: 'Nuevo título',
                tamano: 'mediano',
                alineacion: 'tema',
            };
        case 'texto':
            return {
                ...base,
                texto: 'Escribe aquí. Usa **negritas** o _cursivas_.',
                alineacion: 'tema',
                tamano: 'normal',
            };
        case 'separador':
            return { ...base, estilo: 'linea', alto: 24 };
        case 'grupo':
            return {
                ...base,
                titulo: 'Nueva sección',
                disposicion: 'lista',
                columnas: 2,
                plegable: false,
                abierto: true,
                bloques: [],
            };
        case 'tarjeta':
            return {
                ...base,
                titulo: 'Título de la tarjeta',
                texto: '',
                imagen: null,
                boton: '',
                url: '',
            };
        case 'contacto':
            return {
                ...base,
                titulo: 'Escríbenos',
                descripcion: '',
                pedir_telefono: false,
                boton: 'Enviar mensaje',
                aviso: 'Usaremos tus datos solo para responder a tu mensaje. No los compartimos con terceros.',
                exito: '¡Gracias! Recibimos tu mensaje y te responderemos pronto.',
            };
        case 'qr':
            return {
                ...base,
                titulo: 'Comparte esta página',
                descripcion: 'Escanéalo con la cámara de tu teléfono.',
                tamano: 180,
            };
    }
}

/** Copia con identificadores nuevos (también en elementos internos). */
export function duplicarBloque(bloque: Bloque): Bloque {
    const copia = JSON.parse(JSON.stringify(bloque)) as Bloque;
    copia.id = uuid();
    copia.elementos = copia.elementos?.map((e) => ({ ...e, id: uuid() }));
    copia.bloques = copia.bloques?.map(duplicarBloque);

    return copia;
}

/** Texto corto para identificar el elemento en la lista. */
export function resumenBloque(bloque: Bloque): string {
    switch (bloque.tipo) {
        case 'enlace':
            return bloque.url || 'Falta la dirección';
        case 'whatsapp':
        case 'telefono':
            return bloque.telefono
                ? `+${bloque.telefono.replace(/\D/g, '')}`
                : 'Falta el número';
        case 'correo':
            return bloque.email || 'Falta el correo';
        case 'redes':
            return `${bloque.elementos?.length ?? 0} redes`;
        case 'mapa':
            return bloque.direccion || 'Falta la dirección';
        case 'video':
        case 'musica':
            return (
                bloque.url ||
                (bloque.archivo ? 'Video propio' : 'Falta el enlace')
            );
        case 'carrusel':
            return `${bloque.elementos?.length ?? 0} imágenes`;
        case 'grupo':
            return `${bloque.bloques?.length ?? 0} elementos · ${bloque.disposicion === 'cuadricula' ? 'cuadrícula' : 'lista'}`;
        case 'texto':
            return (bloque.texto ?? '').replace(/\s+/g, ' ').slice(0, 60);
        case 'imagen':
            return (
                bloque.alt || (bloque.archivo ? 'Imagen' : 'Falta la imagen')
            );
        default:
            return '';
    }
}

export const tituloBloque = (bloque: Bloque): string =>
    (bloque.tipo === 'titulo' ? bloque.texto : bloque.titulo) ||
    TIPOS[bloque.tipo]?.etiqueta ||
    'Elemento';
