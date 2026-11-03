/*
 * Service Worker de la PWA de DASTI.
 *
 * Política de caché (deliberadamente mínima, para no guardar información
 * privada en el dispositivo):
 *
 * - Precaché: solo la página sin conexión y los íconos (estáticos, públicos).
 * - Recursos compilados de Vite (/build/assets/*): caché-primero. Son
 *   archivos públicos con hash en el nombre, iguales para todos los usuarios.
 * - Navegaciones: SIEMPRE a la red. Si no hay conexión se muestra
 *   /offline.html; nunca se sirve una página guardada (podría contener datos
 *   de otra sesión o de otro negocio).
 * - Todo lo demás no se intercepta: respuestas de Inertia/JSON, panel
 *   administrativo, autenticación, notificaciones de Openpay, operaciones POST/PUT/
 *   PATCH/DELETE (Fidelidad no funciona sin conexión), redirecciones públicas
 *   /r/{codigo} (siempre consultan el destino vigente), /f/, /mi-fidelidad/,
 *   las Páginas de enlaces públicas /l/ (cada negocio publica cambios en
 *   vivo), los archivos /medios/, el retorno del pago en línea y la
 *   autorización interna de dominios. Los dominios personalizados de los
 *   negocios son otro origen: este Service Worker nunca los controla.
 *
 * Actualización controlada: una versión nueva queda en espera hasta que la
 * interfaz pide activarla (mensaje SKIP_WAITING); al activarse se borran
 * las cachés de versiones anteriores.
 */
const VERSION = 'dasti-pwa-v2';
const CACHE_BASE = `${VERSION}-base`;
const CACHE_ASSETS = `${VERSION}-assets`;
const OFFLINE = '/offline.html';
const PRECACHE = [
    OFFLINE,
    '/manifest.webmanifest',
    '/iconos/icono-192.png',
    '/iconos/icono-512.png',
    '/iconos/icono.svg',
];

// Prefijos que jamás se interceptan ni se guardan.
const NUNCA = [
    '/r/',
    '/f/',
    '/mi-fidelidad',
    '/l/',
    '/medios/',
    '/interno/',
    '/openpay',
    '/panel/servicios/pago',
    '/login',
    '/logout',
    '/two-factor',
    '/user',
    '/password',
    '/invitacion',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_BASE).then((cache) => cache.addAll(PRECACHE)),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((claves) =>
                Promise.all(
                    claves
                        .filter((c) => !c.startsWith(VERSION))
                        .map((c) => caches.delete(c)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    if (event.data === 'SKIP_WAITING') {
        self.skipWaiting();
    }

    // Al cerrar sesión: se descarta todo salvo lo público precargado.
    if (event.data === 'LIMPIAR') {
        event.waitUntil(
            caches
                .keys()
                .then((claves) =>
                    Promise.all(
                        claves
                            .filter((c) => c !== CACHE_BASE)
                            .map((c) => caches.delete(c)),
                    ),
                ),
        );
    }
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (
        url.origin !== self.location.origin ||
        NUNCA.some((p) => url.pathname.startsWith(p))
    ) {
        return;
    }

    // Navegación de página completa: red; sin conexión, aviso.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE)));

        return;
    }

    // Recursos estáticos con hash: caché-primero.
    if (url.pathname.startsWith('/build/assets/')) {
        event.respondWith(
            caches.open(CACHE_ASSETS).then(async (cache) => {
                const guardada = await cache.match(request);

                if (guardada) {
                    return guardada;
                }

                const respuesta = await fetch(request);

                if (respuesta.ok && respuesta.type === 'basic') {
                    cache.put(request, respuesta.clone());
                }

                return respuesta;
            }),
        );

        return;
    }

    if (PRECACHE.includes(url.pathname)) {
        event.respondWith(
            caches.match(request).then((r) => r ?? fetch(request)),
        );
    }
});
