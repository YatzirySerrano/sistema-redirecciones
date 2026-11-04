import type { Component } from 'vue';
import { ICONOS as ICONOS_FIDELIDAD } from '@/components/fidelidad-diseno/catalogo';
import { ICONOS as ICONOS_PAGINAS, REDES } from '@/components/paginas/catalogo';
import { ICONOS_REDES } from '@/components/paginas/iconosRedes';

/**
 * Valores guardados antes de la biblioteca: claves cortas de la Página de
 * enlaces y de Fidelidad (los mismos nombres dibujan el mismo ícono en
 * ambos módulos) y `red:<red>`. Siguen siendo válidos para siempre.
 */
const HEREDADOS: Record<string, { nombre: string; icono: Component }> = {};

for (const [clave, i] of Object.entries(ICONOS_FIDELIDAD)) {
    HEREDADOS[clave] = { nombre: i.nombre, icono: i.icono };
}

for (const [clave, i] of Object.entries(ICONOS_PAGINAS)) {
    HEREDADOS[clave] = { nombre: i.etiqueta, icono: i.icono };
}

/** Equivalente en la biblioteca (para marcarlo como elegido en el selector). */
export const EQUIVALENTES: Record<string, string> = {
    enlace: 'link',
    globo: 'globe',
    mapa: 'map-pin',
    telefono: 'phone',
    correo: 'mail',
    mensaje: 'message-circle',
    estrella: 'star',
    corazon: 'heart',
    info: 'info',
    documento: 'file-text',
    musica: 'eighth-notes',
    video: 'circle-play',
    camara: 'camera',
    imagen: 'image',
    menu: 'utensils-crossed',
    cafe: 'coffee',
    regalo: 'gift',
    etiqueta: 'tag',
    descuento: 'percent',
    reloj: 'clock',
    usuario: 'user',
    equipo: 'users',
    maletin: 'briefcase',
    libro: 'book-open',
    escuela: 'graduation-cap',
    salud: 'heart-pulse',
    brillo: 'sparkles',
    tijeras: 'scissors',
    auto: 'car',
    casa: 'house',
    edificio: 'building',
    boleto: 'ticket',
    noticias: 'newspaper',
    compartir: 'share-2',
    pregunta: 'circle-question-mark',
    escudo: 'shield-check',
    flecha: 'arrow-right',
    wifi: 'wifi',
    hoja: 'leaf',
    pata: 'paw-print',
    paleta: 'palette',
    microfono: 'mic',
    podcast: 'mic-signal',
    trofeo: 'trophy',
    mundo: 'earth',
    pin: 'pin',
    calendario: 'calendar',
    flor: 'flower-2',
    gota: 'droplet',
    check: 'check',
    huella: 'paw-print',
    cubiertos: 'utensils-crossed',
    pastel: 'cake-slice',
    helado: 'ice-cream-cone',
    pizza: 'pizza',
    croissant: 'croissant',
    copa: 'wine',
    diamante: 'gem',
    corona: 'crown',
    destello: 'sparkles',
    sol: 'sun',
    pesa: 'dumbbell',
    pincel: 'paintbrush',
};

/** Componente de un valor heredado o de red; null si no lo es. */
export function componenteHeredado(valor: string): Component | null {
    if (valor.startsWith('red:')) {
        return ICONOS_REDES[valor.slice(4)] ?? null;
    }

    return HEREDADOS[valor]?.icono ?? null;
}

export function nombreHeredado(valor: string): string | null {
    if (valor.startsWith('red:')) {
        return REDES[valor.slice(4)]?.etiqueta ?? null;
    }

    return HEREDADOS[valor]?.nombre ?? null;
}
