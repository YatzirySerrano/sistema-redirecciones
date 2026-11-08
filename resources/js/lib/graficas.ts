/**
 * Utilidades compartidas de las gráficas (SVG propio, sin dependencias).
 *
 * - Colores: siempre tokens CSS, así se adaptan al tema claro/oscuro.
 * - Fechas: las series llegan como 'YYYY-MM-DD' en la zona horaria de la
 *   plataforma (America/Mexico_City) y se interpretan como fecha local, sin
 *   conversión a UTC.
 */

export type Canal = 'nfc' | 'qr' | 'combinado';

export type PuntoCanales = {
    fecha: string;
    total: number;
    nfc: number;
    qr: number;
    combinado: number;
};

export type Agrupacion = 'dia' | 'semana' | 'mes';

export const CANALES: Record<
    Canal,
    { etiqueta: string; corta: string; color: string; descripcion: string }
> = {
    nfc: {
        etiqueta: 'NFC',
        corta: 'NFC',
        color: 'var(--canal-nfc)',
        descripcion: 'Al acercar el teléfono a la tarjeta o etiqueta.',
    },
    qr: {
        etiqueta: 'QR',
        corta: 'QR',
        color: 'var(--canal-qr)',
        descripcion: 'Al escanear el código con la cámara.',
    },
    combinado: {
        etiqueta: 'QR + NFC',
        corta: 'QR+NFC',
        color: 'var(--canal-combinado)',
        descripcion:
            'Tarjetas con un solo enlace para QR y NFC: no es posible saber cuál se usó.',
    },
};

export const ORDEN_CANALES: Canal[] = ['nfc', 'qr', 'combinado'];

/** Paleta categórica para distribuciones sin significado propio. */
export const PALETA = [
    'var(--chart-1)',
    'var(--canal-qr)',
    'var(--chart-4)',
    'var(--chart-5)',
    'var(--chart-2)',
    'var(--chart-3)',
];

const LOCALE = 'es-MX';
const fmtDiaMes = new Intl.DateTimeFormat(LOCALE, {
    day: 'numeric',
    month: 'short',
});
const fmtMes = new Intl.DateTimeFormat(LOCALE, {
    month: 'short',
    year: 'numeric',
});
const fmtMesCorto = new Intl.DateTimeFormat(LOCALE, { month: 'short' });

export function aFechaLocal(valor: string): Date {
    const [a, m, d] = valor.split('-').map(Number);

    return new Date(a, (m ?? 1) - 1, d ?? 1);
}

function iso(fecha: Date): string {
    return `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, '0')}-${String(fecha.getDate()).padStart(2, '0')}`;
}

/** Lunes de la semana de la fecha (semanas de lunes a domingo). */
function lunes(fecha: Date): Date {
    const d = new Date(fecha);
    d.setDate(d.getDate() - ((d.getDay() + 6) % 7));

    return d;
}

export type Grupo = {
    clave: string;
    /** Texto corto para el eje. */
    eje: string;
    /** Texto completo para el tooltip y la tabla. */
    titulo: string;
    /** El grupo no está completo dentro del periodo (semana o mes parcial). */
    parcial: boolean;
    total: number;
    nfc: number;
    qr: number;
    combinado: number;
    /** Índices de los días de la serie original que incluye. */
    dias: number;
};

/**
 * Agrupa una serie diaria por día, semana (lunes a domingo) o mes. Los
 * grupos de los extremos pueden quedar parciales y se marcan como tales.
 */
export function agrupar(serie: PuntoCanales[], modo: Agrupacion): Grupo[] {
    if (modo === 'dia') {
        return serie.map((p) => ({
            ...p,
            clave: p.fecha,
            eje: fmtDiaMes.format(aFechaLocal(p.fecha)),
            titulo: aFechaLocal(p.fecha).toLocaleDateString(LOCALE, {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
            }),
            parcial: false,
            dias: 1,
        }));
    }

    const grupos = new Map<string, Grupo>();

    for (const p of serie) {
        const fecha = aFechaLocal(p.fecha);
        const inicio =
            modo === 'semana'
                ? lunes(fecha)
                : new Date(fecha.getFullYear(), fecha.getMonth(), 1);
        const clave = iso(inicio);
        let g = grupos.get(clave);

        if (!g) {
            const fin =
                modo === 'semana'
                    ? new Date(
                          inicio.getFullYear(),
                          inicio.getMonth(),
                          inicio.getDate() + 6,
                      )
                    : new Date(inicio.getFullYear(), inicio.getMonth() + 1, 0);

            g = {
                clave,
                eje:
                    modo === 'semana'
                        ? fmtDiaMes.format(inicio)
                        : fmtMesCorto.format(inicio),
                titulo:
                    modo === 'semana'
                        ? `Semana del ${fmtDiaMes.format(inicio)} al ${fmtDiaMes.format(fin)}`
                        : fmtMes.format(inicio),
                parcial: false,
                total: 0,
                nfc: 0,
                qr: 0,
                combinado: 0,
                dias: 0,
            };
            grupos.set(clave, g);
        }

        g.total += p.total;
        g.nfc += p.nfc;
        g.qr += p.qr;
        g.combinado += p.combinado;
        g.dias += 1;
    }

    return [...grupos.values()].map((g) => {
        const inicio = aFechaLocal(g.clave);
        const esperados =
            modo === 'semana'
                ? 7
                : new Date(
                      inicio.getFullYear(),
                      inicio.getMonth() + 1,
                      0,
                  ).getDate();

        return { ...g, parcial: g.dias < esperados };
    });
}

/** Agrupación sugerida según la duración del periodo. */
export function agrupacionSugerida(dias: number): Agrupacion {
    if (dias > 120) {
        return 'mes';
    }

    return dias > 45 ? 'semana' : 'dia';
}

/** Máximo «redondo» para el eje (1, 2, 5 × 10ⁿ), nunca menor que 4. */
export function maximoRedondo(valor: number): number {
    if (valor <= 4) {
        return 4;
    }

    const paso = Math.pow(10, Math.floor(Math.log10(valor)));
    const normal = valor / paso;

    return (normal <= 2 ? 2 : normal <= 5 ? 5 : 10) * paso;
}

export type Comparacion = {
    actual: number;
    anterior: number;
    diferencia: number;
    /** null si no hay datos previos con qué comparar. */
    porcentaje: number | null;
    sentido: 'sube' | 'baja' | 'igual';
};

export function comparar(actual: number, anterior: number): Comparacion {
    const diferencia = actual - anterior;

    return {
        actual,
        anterior,
        diferencia,
        porcentaje:
            anterior === 0
                ? null
                : Math.round((diferencia / anterior) * 1000) / 10,
        sentido: diferencia > 0 ? 'sube' : diferencia < 0 ? 'baja' : 'igual',
    };
}

/** Porcentaje de una parte (entero, o con un decimal si es menor que 10). */
export function porcentajeDe(parte: number, total: number): string {
    if (total === 0) {
        return '0 %';
    }

    const valor = (parte / total) * 100;

    return `${valor < 10 && valor > 0 ? valor.toFixed(1) : Math.round(valor)} %`;
}

/** Día con más actividad de una serie (null si no hubo actividad). */
export function diaPico<T extends { fecha: string; total: number }>(
    serie: T[],
): T | null {
    return serie.reduce<T | null>(
        (max, p) => (p.total > 0 && (!max || p.total > max.total) ? p : max),
        null,
    );
}
