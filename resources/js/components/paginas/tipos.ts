import type { MapaIconos } from '@/components/iconos/contexto';

/**
 * Documento de una Página de enlaces. Refleja la forma que produce
 * App\Services\Paginas\DocumentoPagina (el servidor valida y sanea todo
 * antes de guardar o publicar; aquí solo se describe la estructura).
 */

export type Medio = {
    id: string;
    url: string;
    ancho?: number | null;
    alto?: number | null;
};

export type Animacion = 'ninguna' | 'elevar' | 'pulso' | 'brillo';

export type ColoresTema = {
    fondo: string;
    texto: string;
    titulos: string;
    texto_suave: string;
    enlaces: string;
    boton_fondo: string;
    boton_texto: string;
    boton_borde: string;
    boton_hover_fondo: string;
    boton_hover_texto: string;
    tarjeta_fondo: string;
    tarjeta_texto: string;
    borde: string;
    acento: string;
};

export type Encabezado =
    | 'centrado'
    | 'izquierda'
    | 'portada'
    | 'banda'
    | 'tarjeta'
    | 'dividido'
    | 'editorial';

export type Tema = {
    colores: ColoresTema;
    fondo: {
        tipo: 'solido' | 'degradado' | 'imagen' | 'textura' | 'video';
        angulo: number;
        paradas: { color: string; posicion: number }[];
        imagen: Medio | null;
        superposicion: string;
        opacidad: number;
        desenfoque: number;
        textura:
            | 'puntos'
            | 'cuadricula'
            | 'lineas'
            | 'diagonales'
            | 'ondas'
            | 'rombos'
            | 'cruces'
            | 'cuadros'
            | 'aurora';
        color_textura: string;
        video: Medio | null;
        poster: Medio | null;
        ahorro_datos: boolean;
    };
    tipografia: {
        fuente_texto: string;
        fuente_titulos: string;
        tamano: number;
        peso_texto: number;
        peso_titulos: number;
        espaciado: number;
        alineacion: 'centro' | 'izquierda';
        escala_titulos: number;
    };
    botones: {
        estilo:
            | 'solido'
            | 'contorno'
            | 'suave'
            | 'vidrio'
            | 'degradado'
            | 'subrayado';
        radio: number;
        sombra: 'ninguna' | 'suave' | 'media' | 'marcada';
        borde: number;
        tamano: 'compacto' | 'normal' | 'grande';
        animacion: Animacion;
        icono: 'izquierda' | 'derecha' | 'oculto';
        alineacion: 'centro' | 'izquierda';
        /** Opciones añadidas después: pueden faltar en documentos antiguos. */
        icono_fondo?: 'ninguno' | 'suave' | 'solido';
        mayusculas?: boolean;
    };
    disposicion: {
        ancho: 'estrecho' | 'normal' | 'amplio';
        espaciado: 'compacto' | 'normal' | 'amplio';
        encabezado: Encabezado;
        forma_foto: 'circulo' | 'redondeada' | 'cuadrada';
        monograma?: boolean;
        forma_imagen?: 'tema' | 'recta' | 'marco' | 'arco';
        tarjetas?: 'borde' | 'sombra' | 'plana';
        lienzo?: 'libre' | 'panel';
        titulos?: 'simple' | 'linea' | 'etiqueta' | 'barra' | 'resaltado';
    };
};

export type Perfil = {
    nombre: string;
    descripcion: string;
    foto: Medio | null;
    logo: Medio | null;
    portada: Medio | null;
    favicon: Medio | null;
    mostrar_nombre: boolean;
    contacto: { telefono: string; email: string; direccion: string };
};

export type BloqueBase = {
    id: string;
    tipo: TipoBloque;
    oculto?: boolean;
    visible_desde?: string | null;
    visible_hasta?: string | null;
    /** Solo en la página pública: ruta de seguimiento o destino directo. */
    href?: string | null;
    rastreo?: string;
    pagina_slug?: string | null;
};

export type CamposBoton = {
    titulo: string;
    subtitulo: string;
    icono: string | null;
    /** Color y tamaño (px) propios del ícono; null = los del tema. */
    icono_color?: string | null;
    icono_tamano?: number | null;
    miniatura: Medio | null;
    destacado: boolean;
    insignia: string;
    animacion: 'tema' | Animacion;
    tamano: 'normal' | 'grande';
    color_fondo: string | null;
    color_texto: string | null;
};

export type ElementoRed = {
    id: string;
    red: string;
    valor?: string;
    href?: string | null;
};

export type ElementoCarrusel = {
    id: string;
    archivo: Medio | null;
    alt: string;
    url?: string;
    href?: string | null;
};

/**
 * Un elemento de la página. Los campos dependen del tipo; se describen
 * todos como opcionales para que el editor y el renderizador compartan un
 * solo tipo.
 */
export type Bloque = BloqueBase &
    Partial<Omit<CamposBoton, 'tamano'>> & {
        /** Botón: normal|grande; título: grande|mediano|pequeno; QR: píxeles. */
        tamano?: string | number;
        url?: string;
        telefono?: string;
        mensaje?: string;
        email?: string;
        asunto?: string;
        pagina_id?: string;
        estilo?: string;
        elementos?: (ElementoRed & ElementoCarrusel)[];
        direccion?: string;
        mostrar_mapa?: boolean;
        boton?: string;
        fuente?: 'externo' | 'archivo';
        proveedor?: string;
        ref?: string;
        archivo?: Medio | null;
        poster?: Medio | null;
        alt?: string;
        proporcion?: string;
        redondeo?: boolean;
        texto?: string;
        alineacion?: 'tema' | 'izquierda' | 'centro';
        alto?: number;
        disposicion?: 'lista' | 'cuadricula';
        columnas?: 2 | 3;
        plegable?: boolean;
        abierto?: boolean;
        bloques?: Bloque[];
        imagen?: Medio | null;
        descripcion?: string;
        pedir_telefono?: boolean;
        aviso?: string;
        exito?: string;
    };

export type TipoBloque =
    | 'enlace'
    | 'whatsapp'
    | 'telefono'
    | 'correo'
    | 'subpagina'
    | 'redes'
    | 'mapa'
    | 'video'
    | 'musica'
    | 'imagen'
    | 'carrusel'
    | 'titulo'
    | 'texto'
    | 'separador'
    | 'grupo'
    | 'tarjeta'
    | 'contacto'
    | 'qr';

export type Subpagina = {
    id: string;
    slug: string;
    titulo: string;
    descripcion: string;
    bloques: Bloque[];
};

export type Ajustes = {
    seo_titulo: string;
    seo_descripcion: string;
    imagen_social: Medio | null;
    indexar: boolean;
    notificar_mensajes: boolean;
};

export type Documento = {
    esquema: number;
    perfil: Perfil;
    tema: Tema;
    paginas: Subpagina[];
    ajustes: Ajustes;
};

/** Datos que la página pública recibe incrustados por el servidor. */
export type DatosPublicos = {
    documento: Documento;
    pagina: string;
    base: string;
    contacto: { accion: string; marca: string };
    qr: string;
    url_publica: string;
    /** Trazos de los íconos de la biblioteca que usa la página. */
    iconos?: MapaIconos;
};
