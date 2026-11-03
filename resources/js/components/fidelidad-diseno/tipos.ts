/**
 * Documento de apariencia de la tarjeta digital (esquema 1). El servidor lo
 * sanea siempre (app/Services/Fidelidad/Diseno/DocumentoDiseno.php); aquí
 * solo se describe su forma.
 */

export type ClaveFuente =
    | 'inter'
    | 'poppins'
    | 'montserrat'
    | 'nunito'
    | 'quicksand'
    | 'dm-sans'
    | 'josefin-sans'
    | 'space-grotesk'
    | 'playfair-display'
    | 'lora'
    | 'fraunces'
    | 'cormorant-garamond'
    | 'dm-serif-display'
    | 'bebas-neue'
    | 'pacifico'
    | 'caveat';

export type ClaveIcono =
    | 'cafe'
    | 'estrella'
    | 'corazon'
    | 'tijeras'
    | 'flor'
    | 'gota'
    | 'hoja'
    | 'regalo'
    | 'check'
    | 'huella'
    | 'cubiertos'
    | 'pastel'
    | 'helado'
    | 'pizza'
    | 'croissant'
    | 'copa'
    | 'diamante'
    | 'corona'
    | 'destello'
    | 'sol'
    | 'pesa'
    | 'pincel';

export type Presentacion =
    | 'sellos'
    | 'cuadricula'
    | 'circulos'
    | 'barra'
    | 'premium'
    | 'ruta'
    | 'boleto';

export type ClaveDecoracion =
    | 'ninguna'
    | 'puntos'
    | 'ondas'
    | 'granos'
    | 'hojas'
    | 'rayas'
    | 'confeti'
    | 'arcos'
    | 'burbujas'
    | 'diamantes';

export type Sombra = 'ninguna' | 'suave' | 'media' | 'intensa' | 'flotante';

export type TipoFondo = 'solido' | 'degradado' | 'imagen';

export type SeccionInformativa = { titulo: string; elementos: string[] };

export type DocumentoDiseno = {
    esquema: number;
    plantilla: string;
    programa: { nombre: string; lema: string };
    identidad: {
        logo: string | null;
        portada: string | null;
        mostrar_negocio: boolean;
    };
    tipografia: { titulos: ClaveFuente; texto: ClaveFuente };
    colores: {
        primario: string;
        secundario: string;
        texto: string;
        texto_suave: string;
        superficie: string;
        borde: string;
    };
    pagina: {
        fondo_tipo: TipoFondo;
        color1: string;
        color2: string;
        angulo: number;
        imagen: string | null;
        decoracion: ClaveDecoracion;
    };
    tarjeta: {
        orientacion: 'vertical' | 'horizontal';
        fondo_tipo: TipoFondo;
        color1: string;
        color2: string;
        angulo: number;
        imagen: string | null;
        oscurecer: number;
        texto: string;
        texto_suave: string;
        radio: number;
        borde_ancho: number;
        borde_color: string;
        sombra: Sombra;
        decoracion: ClaveDecoracion;
        decoracion_opacidad: number;
    };
    progreso: {
        presentacion: Presentacion;
        icono: ClaveIcono;
        completo_fondo: string;
        completo_icono: string;
        pendiente_fondo: string;
        pendiente_icono: string;
        pendiente_estilo: 'contorno' | 'tenue' | 'punteado';
        mostrar_contador: boolean;
        mostrar_barra: boolean;
        barra_color: string;
        barra_fondo: string;
    };
    contenido: {
        saludo: 'alias' | 'oculto';
        mostrar_campana: boolean;
        mostrar_recompensa: boolean;
        mensaje_progreso: string;
        mensaje_meta: string;
        mensaje_canje: string;
        secciones: SeccionInformativa[];
        terminos: string;
    };
};

export type EstadoPrograma =
    | 'en_progreso'
    | 'disponible'
    | 'canjeada'
    | 'completada';

/** Progreso real (o de ejemplo en la vista previa) de una campaña. */
export type ProgramaPublico = {
    campana: string;
    visitas: number;
    meta: number;
    faltan: number;
    recompensa: string;
    estado: EstadoPrograma;
    recompensas: { descripcion: string; vence_en: string | null }[];
    /** Índice en `disenos.variantes` o null para el diseño general. */
    variante: number | null;
};

/** Datos mínimos y no sensibles que recibe la página del consumidor. */
export type DatosPublicos = {
    negocio: string;
    consumidor: string | null;
    programas: ProgramaPublico[];
    aviso: string | null;
    disenos: { general: DocumentoDiseno; variantes: DocumentoDiseno[] };
    medios: Record<string, string>;
    recuperar_url?: string;
};
