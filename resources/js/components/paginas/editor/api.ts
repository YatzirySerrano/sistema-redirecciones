/**
 * Peticiones JSON del editor al panel (con el token CSRF de Laravel). A
 * diferencia de lib/peticion, conserva los errores por campo (422) y los
 * datos del conflicto de versión (409).
 */

export class ErrorApi extends Error {
    constructor(
        message: string,
        public readonly estado: number,
        public readonly errores: Record<string, string> = {},
        public readonly datos: Record<string, unknown> = {},
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

const MENSAJES: Record<number, string> = {
    401: 'Tu sesión expiró. Recarga la página e inicia sesión de nuevo.',
    403: 'No tienes permiso para realizar esta acción.',
    404: 'El recurso ya no está disponible.',
    413: 'El archivo supera el tamaño permitido. Elige uno más ligero.',
    419: 'Tu sesión expiró. Recarga la página e inicia sesión de nuevo.',
    429: 'Demasiadas solicitudes seguidas. Espera un momento e inténtalo de nuevo.',
};

export async function api<T>(
    url: string,
    opciones: {
        metodo?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
        datos?: unknown;
        formulario?: FormData;
    } = {},
): Promise<T> {
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
                opciones.formulario ??
                (opciones.datos === undefined
                    ? undefined
                    : JSON.stringify(opciones.datos)),
            credentials: 'same-origin',
        });
    } catch {
        throw new ErrorApi(
            'No hay conexión con el servidor. Tus cambios siguen en esta pantalla; inténtalo de nuevo.',
            0,
        );
    }

    const cuerpo = (await respuesta.json().catch(() => ({}))) as Record<
        string,
        unknown
    >;

    if (!respuesta.ok) {
        const errores = Object.fromEntries(
            Object.entries(
                (cuerpo.errors as Record<string, string[]> | undefined) ?? {},
            ).map(([clave, lista]) => [clave, lista[0] ?? '']),
        );
        const mensaje =
            (typeof cuerpo.message === 'string' && cuerpo.message) ||
            MENSAJES[respuesta.status] ||
            'Ocurrió un problema. Inténtalo nuevamente.';

        throw new ErrorApi(mensaje, respuesta.status, errores, cuerpo);
    }

    return cuerpo as T;
}
