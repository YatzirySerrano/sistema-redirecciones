import { contraste, mezclar } from '@/lib/color';
import { familiaCss } from './catalogo';
import type { Bloque, Medio, Tema } from './tipos';

/**
 * Traduce el tema a variables CSS acotadas al contenedor del renderizador
 * (nunca afectan al panel ni a otras páginas). Los valores ya vienen
 * validados por el servidor; aquí se vuelven a filtrar por defensa en
 * profundidad (la vista previa usa el estado local del editor).
 */

const HEX = /^#[0-9a-f]{6}$/i;
const MEDIO = /^\/medios\/[0-9a-f-]{36}\.(webp|png|jpg|gif|mp4|webm)$/;

export const hex = (valor: string | null | undefined, respaldo: string) =>
    valor && HEX.test(valor) ? valor : respaldo;

/** URL de un archivo propio (o null si no tiene la forma esperada). */
export const urlMedio = (medio: Medio | null | undefined): string | null =>
    medio && MEDIO.test(medio.url) ? medio.url : null;

const SOMBRAS: Record<Tema['botones']['sombra'], string> = {
    ninguna: 'none',
    suave: '0 1px 2px rgb(0 0 0 / 0.06), 0 2px 8px -2px rgb(0 0 0 / 0.08)',
    media: '0 2px 4px rgb(0 0 0 / 0.08), 0 10px 24px -8px rgb(0 0 0 / 0.22)',
    marcada: '4px 4px 0 0 var(--pe-boton-borde)',
};

const ANCHOS = { estrecho: '30rem', normal: '36rem', amplio: '46rem' };
const SEPARACION = {
    compacto: '0.625rem',
    normal: '0.875rem',
    amplio: '1.25rem',
};
const ALTURA_BOTON = {
    compacto: '2.875rem',
    normal: '3.5rem',
    grande: '4.25rem',
};

export function variablesTema(tema: Tema): Record<string, string> {
    const c = tema.colores;
    const t = tema.tipografia;
    const b = tema.botones;
    const d = tema.disposicion;

    return {
        '--pe-fondo': hex(c.fondo, '#ffffff'),
        '--pe-texto': hex(c.texto, '#1f1f1f'),
        '--pe-titulos': hex(c.titulos, '#111111'),
        '--pe-suave': hex(c.texto_suave, '#6b6b6b'),
        '--pe-enlaces': hex(c.enlaces, '#1f1f1f'),
        '--pe-boton-fondo': hex(c.boton_fondo, '#ffffff'),
        '--pe-boton-texto': hex(c.boton_texto, '#111111'),
        '--pe-boton-borde': hex(c.boton_borde, '#e5e5e5'),
        '--pe-boton-hover-fondo': hex(c.boton_hover_fondo, '#f5f5f5'),
        '--pe-boton-hover-texto': hex(c.boton_hover_texto, '#111111'),
        '--pe-tarjeta-fondo': hex(c.tarjeta_fondo, '#ffffff'),
        '--pe-tarjeta-texto': hex(c.tarjeta_texto, '#1f1f1f'),
        '--pe-borde': hex(c.borde, '#e5e5e5'),
        '--pe-acento': hex(c.acento, '#111111'),
        '--pe-fuente': familiaCss(t.fuente_texto),
        '--pe-fuente-titulos': familiaCss(t.fuente_titulos),
        '--pe-tamano': `${Math.min(20, Math.max(14, t.tamano))}px`,
        '--pe-peso': String(t.peso_texto),
        '--pe-peso-titulos': String(t.peso_titulos),
        '--pe-espaciado': `${Math.min(10, Math.max(-3, t.espaciado)) / 100}em`,
        '--pe-escala': String(
            Math.min(170, Math.max(100, t.escala_titulos)) / 100,
        ),
        '--pe-radio': `${Math.min(40, Math.max(0, b.radio))}px`,
        '--pe-borde-ancho': `${Math.min(4, Math.max(0, b.borde))}px`,
        '--pe-sombra': SOMBRAS[b.sombra] ?? 'none',
        '--pe-alto-boton': ALTURA_BOTON[b.tamano] ?? ALTURA_BOTON.normal,
        '--pe-ancho': ANCHOS[d.ancho] ?? ANCHOS.normal,
        '--pe-separacion': SEPARACION[d.espaciado] ?? SEPARACION.normal,
    };
}

/** Estilos de la capa de fondo (color, degradado, imagen o textura). */
export function estiloFondo(tema: Tema): Record<string, string> {
    const f = tema.fondo;
    const base = hex(tema.colores.fondo, '#ffffff');

    switch (f.tipo) {
        case 'degradado': {
            const paradas = f.paradas
                .slice(0, 3)
                .map(
                    (p) =>
                        `${hex(p.color, base)} ${Math.min(100, Math.max(0, p.posicion))}%`,
                )
                .join(', ');

            return {
                backgroundColor: base,
                backgroundImage: `linear-gradient(${Math.min(360, Math.max(0, f.angulo))}deg, ${paradas})`,
            };
        }

        case 'textura':
            return {
                backgroundColor: base,
                ...textura(f.textura, hex(f.color_textura, base)),
            };
        default:
            return { backgroundColor: base };
    }
}

function textura(
    clave: Tema['fondo']['textura'],
    color: string,
): Record<string, string> {
    switch (clave) {
        case 'cuadricula':
            return {
                backgroundImage: `linear-gradient(${color} 1px, transparent 1px), linear-gradient(90deg, ${color} 1px, transparent 1px)`,
                backgroundSize: '28px 28px',
            };
        case 'lineas':
            return {
                backgroundImage: `repeating-linear-gradient(0deg, ${color} 0 1px, transparent 1px 16px)`,
            };
        case 'diagonales':
            return {
                backgroundImage: `repeating-linear-gradient(45deg, ${color} 0 1px, transparent 1px 13px)`,
            };
        case 'ondas':
            return {
                backgroundImage: `radial-gradient(circle at 50% 0, transparent 9px, ${color} 10px 11px, transparent 12px)`,
                backgroundSize: '24px 13px',
            };
        case 'rombos':
            return {
                backgroundImage: `repeating-linear-gradient(45deg, ${color} 0 1px, transparent 1px 18px), repeating-linear-gradient(-45deg, ${color} 0 1px, transparent 1px 18px)`,
            };
        default:
            return {
                backgroundImage: `radial-gradient(${color} 1.3px, transparent 1.6px)`,
                backgroundSize: '18px 18px',
            };
    }
}

/** Colores del botón: los del elemento (si los tiene) o los del tema. */
export function variablesBoton(bloque: Bloque): Record<string, string> {
    const fondo =
        bloque.color_fondo && HEX.test(bloque.color_fondo)
            ? bloque.color_fondo
            : null;
    const texto =
        bloque.color_texto && HEX.test(bloque.color_texto)
            ? bloque.color_texto
            : null;
    const variables: Record<string, string> = {};

    if (fondo) {
        variables['--b-fondo'] = fondo;
        variables['--b-borde'] = fondo;
        variables['--b-hover-fondo'] = mezclar(fondo, '#000000', 0.9);
    }

    if (texto) {
        variables['--b-texto'] = texto;
        variables['--b-hover-texto'] = texto;
    }

    return variables;
}

// ---- Accesibilidad: contraste WCAG ----

export type AvisoContraste = {
    clave: string;
    descripcion: string;
    ratio: number;
    minimo: number;
};

/** Color aproximado del fondo (el de base o el promedio del degradado). */
export function fondoAproximado(tema: Tema): string {
    const base = hex(tema.colores.fondo, '#ffffff');

    if (tema.fondo.tipo === 'degradado' && tema.fondo.paradas.length > 0) {
        return tema.fondo.paradas.reduce(
            (acc, p, i) =>
                i === 0
                    ? hex(p.color, base)
                    : mezclar(acc, hex(p.color, base), i / (i + 1)),
            base,
        );
    }

    if (tema.fondo.tipo === 'imagen' || tema.fondo.tipo === 'video') {
        return tema.fondo.opacidad >= 40
            ? mezclar(
                  hex(tema.fondo.superposicion, '#000000'),
                  base,
                  tema.fondo.opacidad / 100,
              )
            : base;
    }

    return base;
}

/** Fondo efectivo del botón según su estilo (sólido, contorno, suave o vidrio). */
export function fondoBoton(tema: Tema, colorBoton?: string | null): string {
    const fondo = fondoAproximado(tema);
    const boton = hex(colorBoton ?? tema.colores.boton_fondo, '#ffffff');

    switch (tema.botones.estilo) {
        case 'contorno':
            return fondo;
        case 'suave':
            return mezclar(boton, fondo, 0.22);
        case 'vidrio':
            return mezclar(boton, fondo, 0.16);
        default:
            return boton;
    }
}

export function avisosContraste(tema: Tema): AvisoContraste[] {
    const c = tema.colores;
    const fondo = fondoAproximado(tema);
    const revisiones: [string, string, string, string, number][] = [
        ['texto', 'Texto sobre el fondo', c.texto, fondo, 4.5],
        ['titulos', 'Títulos sobre el fondo', c.titulos, fondo, 3],
        [
            'texto_suave',
            'Texto secundario sobre el fondo',
            c.texto_suave,
            fondo,
            4.5,
        ],
        [
            'boton_texto',
            'Texto de los botones',
            c.boton_texto,
            fondoBoton(tema),
            4.5,
        ],
        [
            'boton_hover_texto',
            'Texto de los botones al pasar el cursor',
            c.boton_hover_texto,
            tema.botones.estilo === 'solido'
                ? c.boton_hover_fondo
                : mezclar(c.boton_hover_fondo, fondo, 0.5),
            4.5,
        ],
        [
            'tarjeta_texto',
            'Texto de las tarjetas',
            c.tarjeta_texto,
            c.tarjeta_fondo,
            4.5,
        ],
    ];

    return revisiones
        .map(([clave, descripcion, a, b, minimo]) => ({
            clave,
            descripcion,
            ratio:
                Math.round(
                    contraste(hex(a, '#000000'), hex(b, '#ffffff')) * 10,
                ) / 10,
            minimo,
        }))
        .filter((r) => r.ratio < r.minimo);
}

/** Mismo criterio que DocumentoPagina::esVisible (hora local del negocio). */
export function esVisible(bloque: Bloque, ahora: Date = new Date()): boolean {
    if (bloque.oculto) {
        return false;
    }

    const local = (valor: string) => new Date(valor);

    if (bloque.visible_desde && ahora < local(bloque.visible_desde)) {
        return false;
    }

    if (bloque.visible_hasta && ahora >= local(bloque.visible_hasta)) {
        return false;
    }

    return true;
}

export type EstadoProgramacion =
    | 'oculto'
    | 'programado'
    | 'vencido'
    | 'visible'
    | 'temporal';

export function estadoProgramacion(
    bloque: Bloque,
    ahora: Date = new Date(),
): EstadoProgramacion {
    if (bloque.oculto) {
        return 'oculto';
    }

    if (bloque.visible_desde && ahora < new Date(bloque.visible_desde)) {
        return 'programado';
    }

    if (bloque.visible_hasta && ahora >= new Date(bloque.visible_hasta)) {
        return 'vencido';
    }

    return bloque.visible_hasta ? 'temporal' : 'visible';
}
