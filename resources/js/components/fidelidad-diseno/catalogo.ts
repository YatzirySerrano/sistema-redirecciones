/**
 * Catálogo cerrado del diseñador de tarjetas. Debe coincidir con las listas
 * de app/Services/Fidelidad/Diseno/DocumentoDiseno.php (el servidor rechaza
 * cualquier otro valor).
 */
import {
    CakeSlice,
    Check,
    Coffee,
    Croissant,
    Crown,
    Droplet,
    Dumbbell,
    Flower2,
    Gem,
    Gift,
    Heart,
    IceCreamCone,
    Leaf,
    Paintbrush,
    PawPrint,
    Pizza,
    Scissors,
    Sparkles,
    Star,
    Sun,
    UtensilsCrossed,
    Wine,
} from '@lucide/vue';
import type { Component } from 'vue';
import type {
    ClaveDecoracion,
    ClaveFuente,
    ClaveIcono,
    DocumentoDiseno,
    Presentacion,
    Sombra,
    TipoFondo,
} from './tipos';

export const FUENTES: Record<
    ClaveFuente,
    { nombre: string; familia: string; pesos: string; estilo: string }
> = {
    inter: {
        nombre: 'Inter',
        familia: "'Inter', ui-sans-serif, system-ui, sans-serif",
        pesos: '400,500,600,700',
        estilo: 'Moderna y muy legible',
    },
    poppins: {
        nombre: 'Poppins',
        familia: "'Poppins', ui-sans-serif, system-ui, sans-serif",
        pesos: '400,500,600,700',
        estilo: 'Geométrica y amable',
    },
    montserrat: {
        nombre: 'Montserrat',
        familia: "'Montserrat', ui-sans-serif, system-ui, sans-serif",
        pesos: '400,500,600,700',
        estilo: 'Urbana y elegante',
    },
    nunito: {
        nombre: 'Nunito',
        familia: "'Nunito', ui-sans-serif, system-ui, sans-serif",
        pesos: '400,600,700,800',
        estilo: 'Redondeada y cálida',
    },
    quicksand: {
        nombre: 'Quicksand',
        familia: "'Quicksand', ui-sans-serif, system-ui, sans-serif",
        pesos: '400,500,600,700',
        estilo: 'Ligera y suave',
    },
    'dm-sans': {
        nombre: 'DM Sans',
        familia: "'DM Sans', ui-sans-serif, system-ui, sans-serif",
        pesos: '400,500,700',
        estilo: 'Limpia y contemporánea',
    },
    'josefin-sans': {
        nombre: 'Josefin Sans',
        familia: "'Josefin Sans', ui-sans-serif, system-ui, sans-serif",
        pesos: '400,600,700',
        estilo: 'Vintage geométrica',
    },
    'space-grotesk': {
        nombre: 'Space Grotesk',
        familia: "'Space Grotesk', ui-sans-serif, system-ui, sans-serif",
        pesos: '400,500,700',
        estilo: 'Técnica con carácter',
    },
    'playfair-display': {
        nombre: 'Playfair Display',
        familia: "'Playfair Display', ui-serif, Georgia, serif",
        pesos: '400,600,700',
        estilo: 'Editorial y lujosa',
    },
    lora: {
        nombre: 'Lora',
        familia: "'Lora', ui-serif, Georgia, serif",
        pesos: '400,600,700',
        estilo: 'Clásica y cercana',
    },
    fraunces: {
        nombre: 'Fraunces',
        familia: "'Fraunces', ui-serif, Georgia, serif",
        pesos: '400,600,700',
        estilo: 'Artesanal con personalidad',
    },
    'cormorant-garamond': {
        nombre: 'Cormorant Garamond',
        familia: "'Cormorant Garamond', ui-serif, Georgia, serif",
        pesos: '400,600,700',
        estilo: 'Refinada y serena',
    },
    'dm-serif-display': {
        nombre: 'DM Serif Display',
        familia: "'DM Serif Display', ui-serif, Georgia, serif",
        pesos: '400',
        estilo: 'Titulares con contraste',
    },
    'bebas-neue': {
        nombre: 'Bebas Neue',
        familia: "'Bebas Neue', Impact, ui-sans-serif, sans-serif",
        pesos: '400',
        estilo: 'Condensada, solo mayúsculas',
    },
    pacifico: {
        nombre: 'Pacifico',
        familia: "'Pacifico', cursive",
        pesos: '400',
        estilo: 'Manuscrita divertida',
    },
    caveat: {
        nombre: 'Caveat',
        familia: "'Caveat', cursive",
        pesos: '400,600,700',
        estilo: 'Escrita a mano',
    },
};

export const ICONOS: Record<ClaveIcono, { nombre: string; icono: Component }> =
    {
        cafe: { nombre: 'Taza de café', icono: Coffee },
        estrella: { nombre: 'Estrella', icono: Star },
        corazon: { nombre: 'Corazón', icono: Heart },
        tijeras: { nombre: 'Tijeras', icono: Scissors },
        flor: { nombre: 'Flor', icono: Flower2 },
        gota: { nombre: 'Gota', icono: Droplet },
        hoja: { nombre: 'Hoja', icono: Leaf },
        regalo: { nombre: 'Regalo', icono: Gift },
        check: { nombre: 'Palomita', icono: Check },
        huella: { nombre: 'Huella', icono: PawPrint },
        cubiertos: { nombre: 'Cubiertos', icono: UtensilsCrossed },
        pastel: { nombre: 'Pastel', icono: CakeSlice },
        helado: { nombre: 'Helado', icono: IceCreamCone },
        pizza: { nombre: 'Pizza', icono: Pizza },
        croissant: { nombre: 'Pan', icono: Croissant },
        copa: { nombre: 'Copa', icono: Wine },
        diamante: { nombre: 'Diamante', icono: Gem },
        corona: { nombre: 'Corona', icono: Crown },
        destello: { nombre: 'Destellos', icono: Sparkles },
        sol: { nombre: 'Sol', icono: Sun },
        pesa: { nombre: 'Pesa', icono: Dumbbell },
        pincel: { nombre: 'Pincel', icono: Paintbrush },
    };

export const PRESENTACIONES: Record<
    Presentacion,
    { nombre: string; descripcion: string }
> = {
    sellos: {
        nombre: 'Tarjeta de sellos',
        descripcion: 'Sellos redondos con tu icono, como la tarjeta de cartón.',
    },
    cuadricula: {
        nombre: 'Cuadrícula de visitas',
        descripcion: 'Casillas numeradas que se marcan en cada visita.',
    },
    circulos: {
        nombre: 'Círculos que se llenan',
        descripcion: 'Puntos que se rellenan de color con cada visita.',
    },
    barra: {
        nombre: 'Barra de progreso',
        descripcion: 'Una barra limpia con el avance y el contador.',
    },
    premium: {
        nombre: 'Premium con recompensa',
        descripcion: 'La recompensa al centro, con anillo de progreso.',
    },
    ruta: {
        nombre: 'Camino al premio',
        descripcion: 'Un sendero de paradas que termina en tu regalo.',
    },
    boleto: {
        nombre: 'Boleto perforado',
        descripcion: 'Talón con perforaciones, ideal para barberías.',
    },
};

export const DECORACIONES: Record<ClaveDecoracion, string> = {
    ninguna: 'Sin decoración',
    puntos: 'Puntos',
    ondas: 'Ondas',
    granos: 'Granos de café',
    hojas: 'Hojas',
    rayas: 'Rayas diagonales',
    confeti: 'Confeti',
    arcos: 'Arcos',
    burbujas: 'Burbujas',
    diamantes: 'Rombos',
};

export const SOMBRAS: Record<Sombra, { nombre: string; css: string }> = {
    ninguna: { nombre: 'Sin sombra', css: 'none' },
    suave: {
        nombre: 'Suave',
        css: '0 1px 2px rgba(0,0,0,.06), 0 8px 24px -12px rgba(0,0,0,.18)',
    },
    media: {
        nombre: 'Media',
        css: '0 2px 4px rgba(0,0,0,.08), 0 18px 40px -18px rgba(0,0,0,.35)',
    },
    intensa: {
        nombre: 'Intensa',
        css: '0 4px 8px rgba(0,0,0,.12), 0 30px 60px -20px rgba(0,0,0,.5)',
    },
    flotante: {
        nombre: 'Flotante',
        css: '0 40px 80px -30px rgba(0,0,0,.55), 0 0 0 1px rgba(255,255,255,.04) inset',
    },
};

export const TIPOS_FONDO: Record<TipoFondo, string> = {
    solido: 'Color sólido',
    degradado: 'Degradado',
    imagen: 'Imagen',
};

/** Hoja de fonts.bunny.net (sin rastreo) para las tipografías indicadas. */
export function urlFuentes(claves: ClaveFuente[]): string | null {
    const unicas = [...new Set(claves)].filter((c) => c in FUENTES);

    if (unicas.length === 0) {
        return null;
    }

    return `https://fonts.bunny.net/css?family=${unicas.map((c) => `${c}:${FUENTES[c].pesos}`).join('|')}&display=swap`;
}

/** Inserta (una sola vez) las hojas de fuentes en el documento. */
export function cargarFuentes(documentos: DocumentoDiseno[]): void {
    if (typeof document === 'undefined') {
        return;
    }

    for (const doc of documentos) {
        for (const clave of [doc.tipografia.titulos, doc.tipografia.texto]) {
            const url = urlFuentes([clave]);
            const id = `fuente-${clave}`;

            if (!url || document.getElementById(id)) {
                continue;
            }

            const enlace = document.createElement('link');
            enlace.id = id;
            enlace.rel = 'stylesheet';
            enlace.href = url;
            document.head.appendChild(enlace);
        }
    }
}

export function familia(clave: ClaveFuente): string {
    return (FUENTES[clave] ?? FUENTES.inter).familia;
}

/**
 * Fondo CSS a partir de valores YA saneados por el servidor (colores
 * #rrggbb y URLs de /medios). La imagen solo se usa si existe en `medios`.
 */
export function fondoCss(
    tipo: TipoFondo,
    color1: string,
    color2: string,
    angulo: number,
    imagen: string | null,
    medios: Record<string, string>,
    oscurecer = 0,
): string {
    const url = imagen ? medios[imagen] : undefined;

    if (
        tipo === 'imagen' &&
        url &&
        /^\/medios\/[0-9a-f-]{36}\.[a-z]+$/.test(url)
    ) {
        const velo = Math.max(0, Math.min(80, oscurecer)) / 100;

        return `linear-gradient(rgba(0,0,0,${velo}), rgba(0,0,0,${velo})), url("${url}") center / cover no-repeat, ${color1}`;
    }

    if (tipo === 'degradado' || tipo === 'imagen') {
        return `linear-gradient(${angulo}deg, ${color1}, ${color2})`;
    }

    return color1;
}

export function urlMedio(
    uuid: string | null,
    medios: Record<string, string>,
): string | null {
    const url = uuid ? medios[uuid] : undefined;

    return url && /^\/medios\/[0-9a-f-]{36}\.[a-z]+$/.test(url) ? url : null;
}

/** Sustituye {faltan}, {meta}, {visitas} y {recompensa} (texto plano). */
export function plantillaMensaje(
    texto: string,
    valores: {
        faltan: number;
        meta: number;
        visitas: number;
        recompensa: string;
    },
): string {
    return texto
        .replaceAll('{faltan}', String(valores.faltan))
        .replaceAll('{meta}', String(valores.meta))
        .replaceAll('{visitas}', String(valores.visitas))
        .replaceAll('{recompensa}', valores.recompensa);
}
