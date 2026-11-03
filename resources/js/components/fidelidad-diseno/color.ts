/**
 * Utilidades de color y contraste (WCAG 2.x) para avisos de legibilidad.
 */

export function esHex(valor: string): boolean {
    return /^#[0-9a-f]{6}$/i.test(valor);
}

function canales(hex: string): [number, number, number] {
    const limpio = esHex(hex) ? hex.slice(1) : '000000';

    return [0, 2, 4].map((i) => parseInt(limpio.slice(i, i + 2), 16)) as [
        number,
        number,
        number,
    ];
}

function luminancia(hex: string): number {
    const [r, g, b] = canales(hex).map((c) => {
        const v = c / 255;

        return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/** Relación de contraste entre dos colores (1 a 21). */
export function contraste(a: string, b: string): number {
    const [l1, l2] = [luminancia(a), luminancia(b)].sort((x, y) => y - x);

    return (l1 + 0.05) / (l2 + 0.05);
}

/** Mezcla dos colores (t = 0 → a, t = 1 → b). */
export function mezclar(a: string, b: string, t: number): string {
    const ca = canales(a);
    const cb = canales(b);

    return (
        '#' +
        ca
            .map((v, i) =>
                Math.round(v + (cb[i] - v) * t)
                    .toString(16)
                    .padStart(2, '0'),
            )
            .join('')
    );
}

/** Color con transparencia (para fondos sutiles). */
export function conAlfa(hex: string, alfa: number): string {
    const [r, g, b] = canales(hex);

    return `rgba(${r}, ${g}, ${b}, ${Math.max(0, Math.min(1, alfa))})`;
}

/** Negro o blanco, el que mejor se lea sobre el color indicado. */
export function textoSobre(fondo: string): string {
    return contraste(fondo, '#111111') >= contraste(fondo, '#ffffff')
        ? '#111111'
        : '#ffffff';
}
