/**
 * Peticiones del editor de diseño que `pedir` no cubre (archivos y DELETE),
 * con el token CSRF de Laravel y mensajes comprensibles.
 */
import { ErrorPeticion } from '@/lib/peticion';

function xsrf(): string | null {
    const cookie = document.cookie
        .split('; ')
        .find((p) => p.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=')[1]) : null;
}

async function mensaje(r: Response): Promise<string> {
    try {
        const cuerpo = (await r.json()) as {
            message?: string;
            errors?: Record<string, string[]>;
        };
        const primero = cuerpo.errors
            ? Object.values(cuerpo.errors)[0]?.[0]
            : null;

        return primero ?? cuerpo.message ?? 'No pudimos completar la acción.';
    } catch {
        return r.status === 413
            ? 'La imagen es demasiado grande. Elige una más ligera.'
            : 'No pudimos completar la acción. Inténtalo nuevamente.';
    }
}

export async function enviar<T>(
    url: string,
    metodo: 'POST' | 'DELETE',
    cuerpo?: FormData,
): Promise<T> {
    const cabeceras: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    const token = xsrf();

    if (token) {
        cabeceras['X-XSRF-TOKEN'] = token;
    }

    let r: Response;

    try {
        r = await fetch(url, {
            method: metodo,
            headers: cabeceras,
            body: cuerpo,
            credentials: 'same-origin',
        });
    } catch {
        throw new ErrorPeticion(
            'No hay conexión con el servidor. Revisa tu conexión a internet.',
            0,
        );
    }

    if (!r.ok) {
        throw new ErrorPeticion(await mensaje(r), r.status);
    }

    return (await r.json()) as T;
}
