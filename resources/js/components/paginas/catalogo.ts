import {
    ArrowRight,
    BookOpen,
    Briefcase,
    Building2,
    Calendar,
    Camera,
    Car,
    CircleHelp,
    CirclePlay,
    Clock,
    Coffee,
    Earth,
    FileText,
    Gift,
    Globe,
    GraduationCap,
    Heart,
    HeartPulse,
    House,
    Image,
    Info,
    Leaf,
    Link,
    Mail,
    MapPin,
    MessageCircle,
    Mic,
    Music,
    Newspaper,
    Palette,
    PawPrint,
    Percent,
    Phone,
    Pin,
    Podcast,
    Scissors,
    Share2,
    ShieldCheck,
    Sparkles,
    Star,
    Tag,
    Ticket,
    Trophy,
    User,
    Users,
    UtensilsCrossed,
    Wifi,
} from '@lucide/vue';
import type { Component } from 'vue';
import { ICONOS_REDES } from './iconosRedes';

/**
 * Catálogo de presentación (mismas claves que App\Services\Paginas\
 * CatalogoPagina). El servidor descarta cualquier valor que no esté allí.
 */

export const ICONOS: Record<string, { etiqueta: string; icono: Component }> = {
    enlace: { etiqueta: 'Enlace', icono: Link },
    globo: { etiqueta: 'Sitio web', icono: Globe },
    mapa: { etiqueta: 'Ubicación', icono: MapPin },
    telefono: { etiqueta: 'Teléfono', icono: Phone },
    correo: { etiqueta: 'Correo', icono: Mail },
    mensaje: { etiqueta: 'Mensaje', icono: MessageCircle },
    estrella: { etiqueta: 'Estrella', icono: Star },
    corazon: { etiqueta: 'Corazón', icono: Heart },
    info: { etiqueta: 'Información', icono: Info },
    documento: { etiqueta: 'Documento', icono: FileText },
    musica: { etiqueta: 'Música', icono: Music },
    video: { etiqueta: 'Video', icono: CirclePlay },
    camara: { etiqueta: 'Fotos', icono: Camera },
    imagen: { etiqueta: 'Imagen', icono: Image },
    menu: { etiqueta: 'Menú', icono: UtensilsCrossed },
    cafe: { etiqueta: 'Café', icono: Coffee },
    regalo: { etiqueta: 'Regalo', icono: Gift },
    etiqueta: { etiqueta: 'Etiqueta', icono: Tag },
    descuento: { etiqueta: 'Descuento', icono: Percent },
    reloj: { etiqueta: 'Horario', icono: Clock },
    usuario: { etiqueta: 'Persona', icono: User },
    equipo: { etiqueta: 'Equipo', icono: Users },
    maletin: { etiqueta: 'Trabajo', icono: Briefcase },
    libro: { etiqueta: 'Libro', icono: BookOpen },
    escuela: { etiqueta: 'Cursos', icono: GraduationCap },
    salud: { etiqueta: 'Salud', icono: HeartPulse },
    brillo: { etiqueta: 'Destellos', icono: Sparkles },
    tijeras: { etiqueta: 'Estética', icono: Scissors },
    auto: { etiqueta: 'Auto', icono: Car },
    casa: { etiqueta: 'Casa', icono: House },
    edificio: { etiqueta: 'Oficina', icono: Building2 },
    boleto: { etiqueta: 'Boletos', icono: Ticket },
    noticias: { etiqueta: 'Noticias', icono: Newspaper },
    compartir: { etiqueta: 'Compartir', icono: Share2 },
    pregunta: { etiqueta: 'Preguntas', icono: CircleHelp },
    escudo: { etiqueta: 'Garantía', icono: ShieldCheck },
    flecha: { etiqueta: 'Flecha', icono: ArrowRight },
    wifi: { etiqueta: 'Wi-Fi', icono: Wifi },
    hoja: { etiqueta: 'Natural', icono: Leaf },
    pata: { etiqueta: 'Mascotas', icono: PawPrint },
    paleta: { etiqueta: 'Arte', icono: Palette },
    microfono: { etiqueta: 'Micrófono', icono: Mic },
    podcast: { etiqueta: 'Podcast', icono: Podcast },
    trofeo: { etiqueta: 'Logros', icono: Trophy },
    mundo: { etiqueta: 'Mundo', icono: Earth },
    pin: { etiqueta: 'Fijado', icono: Pin },
    calendario: { etiqueta: 'Fechas', icono: Calendar },
};

export const REDES: Record<
    string,
    { etiqueta: string; placeholder: string; esTelefono?: boolean }
> = {
    instagram: {
        etiqueta: 'Instagram',
        placeholder: 'https://instagram.com/tu-negocio',
    },
    facebook: {
        etiqueta: 'Facebook',
        placeholder: 'https://facebook.com/tu-negocio',
    },
    tiktok: {
        etiqueta: 'TikTok',
        placeholder: 'https://tiktok.com/@tu-negocio',
    },
    x: { etiqueta: 'X', placeholder: 'https://x.com/tu-negocio' },
    youtube: {
        etiqueta: 'YouTube',
        placeholder: 'https://youtube.com/@tu-negocio',
    },
    linkedin: {
        etiqueta: 'LinkedIn',
        placeholder: 'https://linkedin.com/company/tu-negocio',
    },
    whatsapp: {
        etiqueta: 'WhatsApp',
        placeholder: '55 1234 5678',
        esTelefono: true,
    },
    telegram: { etiqueta: 'Telegram', placeholder: 'https://t.me/tu-negocio' },
    pinterest: {
        etiqueta: 'Pinterest',
        placeholder: 'https://pinterest.com/tu-negocio',
    },
    threads: {
        etiqueta: 'Threads',
        placeholder: 'https://threads.net/@tu-negocio',
    },
    spotify: {
        etiqueta: 'Spotify',
        placeholder: 'https://open.spotify.com/artist/…',
    },
    sitio: { etiqueta: 'Sitio web', placeholder: 'https://tu-negocio.com' },
};

/** Ícono por clave: de la lista curada o de una red («red:instagram»). */
export function componenteIcono(
    clave: string | null | undefined,
): Component | null {
    if (!clave) {
        return null;
    }

    if (clave.startsWith('red:')) {
        return ICONOS_REDES[clave.slice(4)] ?? null;
    }

    return ICONOS[clave]?.icono ?? null;
}

export const FUENTES: Record<
    string,
    { etiqueta: string; familia: string; categoria: 'sans' | 'serif' }
> = {
    sistema: {
        etiqueta: 'Del sistema',
        familia:
            'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
        categoria: 'sans',
    },
    inter: { etiqueta: 'Inter', familia: '"Inter"', categoria: 'sans' },
    'dm-sans': { etiqueta: 'DM Sans', familia: '"DM Sans"', categoria: 'sans' },
    manrope: { etiqueta: 'Manrope', familia: '"Manrope"', categoria: 'sans' },
    'plus-jakarta-sans': {
        etiqueta: 'Plus Jakarta Sans',
        familia: '"Plus Jakarta Sans"',
        categoria: 'sans',
    },
    outfit: { etiqueta: 'Outfit', familia: '"Outfit"', categoria: 'sans' },
    poppins: { etiqueta: 'Poppins', familia: '"Poppins"', categoria: 'sans' },
    sora: { etiqueta: 'Sora', familia: '"Sora"', categoria: 'sans' },
    'space-grotesk': {
        etiqueta: 'Space Grotesk',
        familia: '"Space Grotesk"',
        categoria: 'sans',
    },
    'work-sans': {
        etiqueta: 'Work Sans',
        familia: '"Work Sans"',
        categoria: 'sans',
    },
    nunito: { etiqueta: 'Nunito', familia: '"Nunito"', categoria: 'sans' },
    'josefin-sans': {
        etiqueta: 'Josefin Sans',
        familia: '"Josefin Sans"',
        categoria: 'sans',
    },
    'bricolage-grotesque': {
        etiqueta: 'Bricolage Grotesque',
        familia: '"Bricolage Grotesque"',
        categoria: 'sans',
    },
    lora: { etiqueta: 'Lora', familia: '"Lora"', categoria: 'serif' },
    'playfair-display': {
        etiqueta: 'Playfair Display',
        familia: '"Playfair Display"',
        categoria: 'serif',
    },
    fraunces: {
        etiqueta: 'Fraunces',
        familia: '"Fraunces"',
        categoria: 'serif',
    },
    'cormorant-garamond': {
        etiqueta: 'Cormorant Garamond',
        familia: '"Cormorant Garamond"',
        categoria: 'serif',
    },
    'libre-baskerville': {
        etiqueta: 'Libre Baskerville',
        familia: '"Libre Baskerville"',
        categoria: 'serif',
    },
    'dm-serif-display': {
        etiqueta: 'DM Serif Display',
        familia: '"DM Serif Display"',
        categoria: 'serif',
    },
};

export function familiaCss(clave: string): string {
    const fuente = FUENTES[clave] ?? FUENTES.sistema;
    const respaldo =
        fuente.categoria === 'serif'
            ? 'ui-serif, Georgia, serif'
            : 'ui-sans-serif, system-ui, sans-serif';

    return clave === 'sistema'
        ? fuente.familia
        : `${fuente.familia}, ${respaldo}`;
}

/** Misma URL que App\Services\Paginas\FuentesPagina::url(). */
export function urlFuentes(tipografia: {
    fuente_texto: string;
    fuente_titulos: string;
    peso_texto: number;
    peso_titulos: number;
}): string | null {
    const familias = new Map<string, number[]>();
    const agregar = (clave: string, pesos: number[]) => {
        if (clave === 'sistema' || !(clave in FUENTES)) {
            return;
        }

        familias.set(clave, [...(familias.get(clave) ?? []), ...pesos]);
    };

    agregar(tipografia.fuente_texto, [tipografia.peso_texto, 600, 700]);
    agregar(tipografia.fuente_titulos, [tipografia.peso_titulos]);

    if (familias.size === 0) {
        return null;
    }

    const partes = [...familias].map(
        ([clave, pesos]) =>
            `${clave}:${[...new Set(pesos)].sort((a, b) => a - b).join(',')}`,
    );

    return `https://fonts.bunny.net/css?family=${partes.join('|')}&display=swap`;
}

/** Carga (o actualiza) la hoja de fonts.bunny.net en el documento. */
export function asegurarFuentes(url: string | null): void {
    if (typeof document === 'undefined' || !url) {
        return;
    }

    const existente = document.getElementById(
        'pagina-fuentes',
    ) as HTMLLinkElement | null;

    if (existente) {
        if (existente.href !== url) {
            existente.href = url;
        }

        return;
    }

    const enlace = document.createElement('link');
    enlace.id = 'pagina-fuentes';
    enlace.rel = 'stylesheet';
    enlace.href = url;
    document.head.appendChild(enlace);
}

export const TEXTURAS: Record<string, string> = {
    puntos: 'Puntos',
    cuadricula: 'Cuadrícula',
    lineas: 'Líneas',
    diagonales: 'Diagonales',
    ondas: 'Ondas',
    rombos: 'Rombos',
    cruces: 'Cruces',
    cuadros: 'Cuadros',
    aurora: 'Resplandor',
};
