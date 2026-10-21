/**
 * Peticiones fetch al propio backend (fuera de Inertia) con el token CSRF de
 * Laravel y mensajes de error coherentes para la interfaz.
 */

export class ErrorPeticion extends Error {
    constructor(
        message: string,
        public readonly estado: number,
    ) {
        super(message);
    }
}

function tokenXsrf(): string | null {
    const cookie = document.cookie
        .split('; ')
        .find((parte) => parte.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=')[1]) : null;
}

/** Mensaje para el usuario según el estado HTTP de la respuesta. */
async function mensajeDeError(respuesta: Response): Promise<string> {
    if (respuesta.status === 401 || respuesta.status === 419) {
        return 'Tu sesión expiró. Recarga la página e inicia sesión de nuevo.';
    }

    if (respuesta.status === 403) {
        return 'Tu cuenta no tiene permiso para realizar esta acción.';
    }

    if (respuesta.status === 404) {
        return 'El recurso ya no está disponible.';
    }

    if (respuesta.status === 429) {
        return 'Demasiadas solicitudes seguidas. Espera un momento e inténtalo de nuevo.';
    }

    if (respuesta.status === 422) {
        try {
            const cuerpo = (await respuesta.json()) as { message?: string };

            if (cuerpo.message) {
                return cuerpo.message;
            }
        } catch {
            // Respuesta sin JSON: se usa el mensaje genérico.
        }

        return 'Los datos enviados no son válidos.';
    }

    return 'Ocurrió un error en el servidor. Inténtalo de nuevo en unos minutos.';
}

/**
 * Ejecuta la petición y devuelve la respuesta si fue correcta; si no, lanza
 * ErrorPeticion con un mensaje apto para mostrarse.
 */
export async function pedir(
    url: string,
    opciones: {
        metodo?: 'GET' | 'POST';
        datos?: unknown;
        senal?: AbortSignal;
    } = {},
): Promise<Response> {
    const cabeceras: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    const xsrf = tokenXsrf();

    if (xsrf) {
        cabeceras['X-XSRF-TOKEN'] = xsrf;
    }

    if (opciones.datos !== undefined) {
        cabeceras['Content-Type'] = 'application/json';
    }

    let respuesta: Response;

    try {
        respuesta = await fetch(url, {
            method: opciones.metodo ?? 'GET',
            headers: cabeceras,
            body:
                opciones.datos === undefined
                    ? undefined
                    : JSON.stringify(opciones.datos),
            credentials: 'same-origin',
            signal: opciones.senal,
        });
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            throw error;
        }

        throw new ErrorPeticion(
            'No hay conexión con el servidor. Revisa tu conexión a internet.',
            0,
        );
    }

    if (!respuesta.ok) {
        throw new ErrorPeticion(
            await mensajeDeError(respuesta),
            respuesta.status,
        );
    }

    return respuesta;
}
