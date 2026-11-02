/**
 * Utilidades de color para la vista previa de «Apariencia». Replican
 * App\Services\Apariencia\Color y TemaVisual para mostrar el resultado y
 * las advertencias de contraste antes de guardar; el servidor vuelve a
 * validarlo.
 */

export const TEXTO_OSCURO = '#17141f';
export const TEXTO_CLARO = '#ffffff';
const TARJETA_CLARA = '#ffffff';

export type Colores = Record<
    | 'principal'
    | 'secundario'
    | 'acento'
    | 'botones'
    | 'enlaces'
    | 'navegacion'
    | 'fondo',
    string
>;

export const esHex = (v: string) => /^#[0-9a-f]{6}$/i.test(v);

const rgb = (hex: string): [number, number, number] => [
    parseInt(hex.slice(1, 3), 16),
    parseInt(hex.slice(3, 5), 16),
    parseInt(hex.slice(5, 7), 16),
];

const aHex = (r: number, g: number, b: number) =>
    '#' +
    [r, g, b]
        .map((v) =>
            Math.round(Math.max(0, Math.min(255, v)))
                .toString(16)
                .padStart(2, '0'),
        )
        .join('');

export function luminancia(hex: string): number {
    const canal = (v: number) => {
        const s = v / 255;

        return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
    };
    const [r, g, b] = rgb(hex);

    return 0.2126 * canal(r) + 0.7152 * canal(g) + 0.0722 * canal(b);
}

export function contraste(a: string, b: string): number {
    const la = luminancia(a);
    const lb = luminancia(b);

    return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
}

export const primerPlano = (fondo: string) =>
    contraste(fondo, TEXTO_CLARO) >= contraste(fondo, TEXTO_OSCURO)
        ? TEXTO_CLARO
        : TEXTO_OSCURO;

export function mezclar(a: string, b: string, peso: number): string {
    const [r1, g1, b1] = rgb(a);
    const [r2, g2, b2] = rgb(b);

    return aHex(
        r1 * peso + r2 * (1 - peso),
        g1 * peso + g2 * (1 - peso),
        b1 * peso + b2 * (1 - peso),
    );
}

export function ajustarContraste(
    color: string,
    fondo: string,
    minimo: number,
): string {
    if (contraste(color, fondo) >= minimo) {
        return color;
    }

    const destino = luminancia(fondo) < 0.5 ? '#ffffff' : '#000000';

    for (let paso = 0.05; paso <= 1.0001; paso += 0.05) {
        const candidato = mezclar(destino, color, paso);

        if (contraste(candidato, fondo) >= minimo) {
            return candidato;
        }
    }

    return destino;
}

/** Variables CSS del modo claro (igual que TemaVisual::calcularTokens). */
export function tokensClaro(c: Colores): Record<string, string> {
    const navTexto = primerPlano(c.navegacion);
    const navClara = navTexto === TEXTO_OSCURO;
    const acentoTexto =
        contraste(c.principal, c.acento) >= 4.5
            ? c.principal
            : primerPlano(c.acento);
    const activo = ajustarContraste(c.principal, c.navegacion, 2);

    return {
        '--background': c.fondo,
        '--primary': c.principal,
        '--primary-foreground': primerPlano(c.principal),
        '--ring': c.principal,
        '--chart-1': c.principal,
        '--chart-2': mezclar(c.principal, '#ffffff', 0.55),
        '--secondary': c.secundario,
        '--secondary-foreground': primerPlano(c.secundario),
        '--accent': c.acento,
        '--accent-foreground': acentoTexto,
        '--boton': c.botones,
        '--boton-foreground': primerPlano(c.botones),
        '--enlace': c.enlaces,
        '--sidebar-background': c.navegacion,
        '--sidebar': c.navegacion,
        '--sidebar-foreground': mezclar(navTexto, c.navegacion, 0.78),
        '--sidebar-accent': mezclar(
            navTexto,
            c.navegacion,
            navClara ? 0.07 : 0.1,
        ),
        '--sidebar-accent-foreground': navTexto,
        '--sidebar-border': mezclar(navTexto, c.navegacion, 0.1),
        '--sidebar-primary': activo,
        '--sidebar-primary-foreground': primerPlano(activo),
        '--sidebar-ring': activo,
    };
}

/** Combinaciones ilegibles (igual que TemaVisual::problemasDeContraste). */
export function problemasDeContraste(
    c: Colores,
): Partial<Record<keyof Colores, string>> {
    const problemas: Partial<Record<keyof Colores, string>> = {};

    if (contraste(TEXTO_OSCURO, c.fondo) < 7) {
        problemas.fondo =
            'El fondo es demasiado oscuro para leer el texto. Elige un tono más claro.';
    }

    if (
        Math.min(
            contraste(c.enlaces, c.fondo),
            contraste(c.enlaces, TARJETA_CLARA),
        ) < 4.5
    ) {
        problemas.enlaces =
            'Los enlaces no se distinguen bien sobre el fondo. Elige un tono más intenso.';
    }

    if (
        Math.min(
            contraste(c.principal, c.fondo),
            contraste(c.principal, TARJETA_CLARA),
        ) < 3
    ) {
        problemas.principal =
            'El color principal no se distingue bien sobre el fondo. Elige un tono más intenso.';
    }

    if (
        contraste(c.botones, c.fondo) < 3 ||
        contraste(c.botones, TARJETA_CLARA) < 3
    ) {
        problemas.botones =
            'Los botones no se distinguen bien sobre el fondo. Elige un tono más intenso.';
    }

    if (contraste(c.navegacion, c.fondo) < 1.25) {
        problemas.navegacion =
            'La barra lateral se confunde con el fondo. Elige un tono distinto.';
    }

    return problemas;
}
