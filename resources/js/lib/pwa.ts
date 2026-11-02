import { ref } from 'vue';
import { toast } from 'vue-sonner';

/**
 * Aplicación web progresiva: registro del Service Worker (public/sw.js),
 * aviso de versión nueva, instalación y estado de conexión.
 *
 * El Service Worker solo guarda recursos públicos (ver su encabezado); los
 * datos de la cuenta nunca quedan en caché.
 */

type EventoInstalacion = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

export const enLinea = ref(
    typeof navigator === 'undefined' ? true : navigator.onLine,
);
export const instalable = ref(false);
let solicitudInstalacion: EventoInstalacion | null = null;

export async function instalar(): Promise<void> {
    if (!solicitudInstalacion) {
        return;
    }

    await solicitudInstalacion.prompt();
    await solicitudInstalacion.userChoice;
    solicitudInstalacion = null;
    instalable.value = false;
}

/** Al cerrar sesión: descarta cualquier caché que no sea pública. */
export function limpiarCacheAlSalir(): void {
    navigator.serviceWorker?.controller?.postMessage('LIMPIAR');
}

function avisarActualizacion(registro: ServiceWorkerRegistration): void {
    toast.info('Hay una versión nueva de la plataforma.', {
        duration: Infinity,
        action: {
            label: 'Actualizar',
            onClick: () => registro.waiting?.postMessage('SKIP_WAITING'),
        },
    });
}

export function initializePwa(): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.addEventListener('online', () => (enLinea.value = true));
    window.addEventListener('offline', () => (enLinea.value = false));

    window.addEventListener('beforeinstallprompt', (evento) => {
        evento.preventDefault();
        solicitudInstalacion = evento as EventoInstalacion;
        instalable.value = true;
    });
    window.addEventListener('appinstalled', () => {
        solicitudInstalacion = null;
        instalable.value = false;
    });

    if (!('serviceWorker' in navigator) || !window.isSecureContext) {
        return;
    }

    // Solo se recarga al reemplazar una versión anterior (tras «Actualizar»);
    // la primera instalación toma el control sin interrumpir al usuario.
    const habiaVersionAnterior = navigator.serviceWorker.controller !== null;
    let recargando = false;
    navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (habiaVersionAnterior && !recargando) {
            recargando = true;
            window.location.reload();
        }
    });

    window.addEventListener('load', async () => {
        try {
            const registro = await navigator.serviceWorker.register('/sw.js', {
                scope: '/',
            });

            if (registro.waiting && navigator.serviceWorker.controller) {
                avisarActualizacion(registro);
            }

            registro.addEventListener('updatefound', () => {
                const nuevo = registro.installing;
                nuevo?.addEventListener('statechange', () => {
                    if (
                        nuevo.state === 'installed' &&
                        navigator.serviceWorker.controller
                    ) {
                        avisarActualizacion(registro);
                    }
                });
            });
        } catch {
            // Sin Service Worker la aplicación funciona igual (sin instalación).
        }
    });
}
