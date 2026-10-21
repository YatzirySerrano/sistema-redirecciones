/**
 * Formateo consistente (es-MX) de fechas, números y URLs.
 */

const LOCALE = 'es-MX';

const fechaCortaFmt = new Intl.DateTimeFormat(LOCALE, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

const fechaHoraFmt = new Intl.DateTimeFormat(LOCALE, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

const diaMesFmt = new Intl.DateTimeFormat(LOCALE, {
    day: 'numeric',
    month: 'short',
});

const numeroFmt = new Intl.NumberFormat(LOCALE);
const compactoFmt = new Intl.NumberFormat(LOCALE, {
    notation: 'compact',
    maximumFractionDigits: 1,
});
const relativoFmt = new Intl.RelativeTimeFormat(LOCALE, { numeric: 'auto' });

/** Interpreta 'YYYY-MM-DD' como fecha local (sin desfase de zona). */
function aFecha(valor: string): Date {
    return /^\d{4}-\d{2}-\d{2}$/.test(valor)
        ? new Date(`${valor}T00:00:00`)
        : new Date(valor);
}

export function fechaCorta(valor: string | null | undefined): string {
    return valor ? fechaCortaFmt.format(aFecha(valor)) : '—';
}

export function fechaHora(valor: string | null | undefined): string {
    return valor ? fechaHoraFmt.format(aFecha(valor)) : '—';
}

export function diaMes(valor: string): string {
    return diaMesFmt.format(aFecha(valor));
}

export function numero(valor: number | null | undefined): string {
    return numeroFmt.format(valor ?? 0);
}

export function compacto(valor: number): string {
    return valor < 1000 ? numeroFmt.format(valor) : compactoFmt.format(valor);
}

export function relativo(valor: string | null | undefined): string {
    if (!valor) {
        return 'Sin registro';
    }

    const segundos = (aFecha(valor).getTime() - Date.now()) / 1000;
    const unidades: [Intl.RelativeTimeFormatUnit, number][] = [
        ['year', 31536000],
        ['month', 2592000],
        ['week', 604800],
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];

    for (const [unidad, tamano] of unidades) {
        if (Math.abs(segundos) >= tamano) {
            return relativoFmt.format(Math.round(segundos / tamano), unidad);
        }
    }

    return 'hace un momento';
}

/** Dominio de una URL (sin "www."), o el texto original si no es válida. */
export function dominio(url: string | null | undefined): string {
    if (!url) {
        return '';
    }

    try {
        return new URL(url).hostname.replace(/^www\./, '');
    } catch {
        return url;
    }
}

/** Fecha de hoy en formato YYYY-MM-DD (zona local). */
export function hoyIso(): string {
    const d = new Date();

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}
