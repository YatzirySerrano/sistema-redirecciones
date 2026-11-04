/**
 * Validación de archivos ANTES de subirlos (tamaño, extensión y formato
 * real por su firma binaria), con mensajes claros en español. Los límites
 * vienen del servidor (App\Services\Medios\LimitesArchivos, compartidos en
 * `page.props.limites_archivos`), que vuelve a validar todo.
 */
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export type TipoLimite = 'logo' | 'imagen' | 'video';

export type LimiteArchivo = {
    tipo: TipoLimite;
    max_bytes: number;
    max_texto: string;
    formatos: string[];
    formatos_texto: string;
    extensiones: string[];
    mimes: string[];
    aceptar: string;
    recomendacion: string;
};

/** Valores de respaldo si la página no recibió los límites. */
const RESPALDO: Record<TipoLimite, LimiteArchivo> = {
    logo: {
        tipo: 'logo',
        max_bytes: 2 * 1048576,
        max_texto: '2 MB',
        formatos: ['PNG', 'JPG', 'WEBP'],
        formatos_texto: 'PNG, JPG o WEBP',
        extensiones: ['png', 'jpg', 'jpeg', 'webp'],
        mimes: ['image/png', 'image/jpeg', 'image/webp'],
        aceptar: 'image/png,image/jpeg,image/webp,.png,.jpg,.jpeg,.webp',
        recomendacion: '',
    },
    imagen: {
        tipo: 'imagen',
        max_bytes: 2 * 1048576,
        max_texto: '2 MB',
        formatos: ['PNG', 'JPG', 'WEBP', 'GIF'],
        formatos_texto: 'PNG, JPG, WEBP o GIF',
        extensiones: ['png', 'jpg', 'jpeg', 'webp', 'gif'],
        mimes: ['image/png', 'image/jpeg', 'image/webp', 'image/gif'],
        aceptar:
            'image/png,image/jpeg,image/webp,image/gif,.png,.jpg,.jpeg,.webp,.gif',
        recomendacion: '',
    },
    video: {
        tipo: 'video',
        max_bytes: 2 * 1048576,
        max_texto: '2 MB',
        formatos: ['MP4', 'WEBM'],
        formatos_texto: 'MP4 o WEBM',
        extensiones: ['mp4', 'webm'],
        mimes: ['video/mp4', 'video/webm'],
        aceptar: 'video/mp4,video/webm,.mp4,.webm',
        recomendacion: '',
    },
};

export function useLimitesArchivos() {
    const page = usePage();
    const limites = computed(
        () =>
            (page.props.limites_archivos as Record<
                TipoLimite,
                LimiteArchivo
            > | null) ?? RESPALDO,
    );

    return {
        limites,
        limite: (tipo: TipoLimite) => limites.value[tipo] ?? RESPALDO[tipo],
    };
}

/** «PNG, JPG o WEBP. Tamaño máximo: 10 MB». */
export function textoLimite(l: LimiteArchivo): string {
    return `${l.formatos_texto}. Tamaño máximo: ${l.max_texto}.`;
}

export function tamanoLegible(bytes: number): string {
    if (bytes >= 1048576) {
        const mb = bytes / 1048576;

        return `${mb.toLocaleString('es-MX', { maximumFractionDigits: mb >= 10 ? 0 : 1 })} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/** Formato real según los primeros bytes (no la extensión). */
async function formatoReal(archivo: File): Promise<string | null> {
    const b = new Uint8Array(await archivo.slice(0, 16).arrayBuffer());
    const ascii = (i: number, n: number) =>
        String.fromCharCode(...b.slice(i, i + n));

    if (b[0] === 0x89 && ascii(1, 3) === 'PNG') {
        return 'PNG';
    }

    if (b[0] === 0xff && b[1] === 0xd8 && b[2] === 0xff) {
        return 'JPG';
    }

    if (ascii(0, 4) === 'RIFF' && ascii(8, 4) === 'WEBP') {
        return 'WEBP';
    }

    if (ascii(0, 4) === 'GIF8') {
        return 'GIF';
    }

    if (ascii(4, 4) === 'ftyp') {
        return 'MP4';
    }

    if (b[0] === 0x1a && b[1] === 0x45 && b[2] === 0xdf && b[3] === 0xa3) {
        return 'WEBM';
    }

    return null;
}

/**
 * Devuelve un mensaje de error comprensible o null si el archivo es válido.
 */
export async function validarArchivo(
    archivo: File,
    limite: LimiteArchivo,
): Promise<string | null> {
    const extension = archivo.name.split('.').pop()?.toLowerCase() ?? '';

    if (!limite.extensiones.includes(extension)) {
        return `«${archivo.name}» no es un formato permitido. Usa ${limite.formatos_texto}.`;
    }

    if (archivo.size > limite.max_bytes) {
        return `El archivo pesa ${tamanoLegible(archivo.size)} y el máximo es ${limite.max_texto}. Elige uno más ligero o redúcelo antes de subirlo.`;
    }

    if (archivo.size === 0) {
        return 'El archivo está vacío. Elige otro.';
    }

    const real = await formatoReal(archivo).catch(() => null);

    if (real === null || !limite.formatos.includes(real)) {
        return `El contenido de «${archivo.name}» no corresponde a ${limite.formatos_texto}. Puede estar dañado o tener una extensión cambiada.`;
    }

    return null;
}

/**
 * Traduce errores HTTP de una subida a mensajes para el usuario (nunca
 * «413» ni «The file is too large»).
 */
export function mensajeErrorSubida(
    estado: number,
    limite: LimiteArchivo,
    mensajeServidor?: string | null,
): string {
    if (estado === 413) {
        return `El archivo es demasiado grande. ${textoLimite(limite)}`;
    }

    if (
        mensajeServidor &&
        !/[a-z]+ (is|must|failed|too)/i.test(mensajeServidor)
    ) {
        return mensajeServidor;
    }

    return `No pudimos subir el archivo. ${textoLimite(limite)}`;
}
