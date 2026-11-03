/**
 * Utilidades de contenido compartidas por el editor y la página pública:
 * texto enriquecido mínimo y reproductores insertados.
 */

export type Segmento = { texto: string; negrita?: boolean; cursiva?: boolean };

/**
 * Formato permitido: **negritas**, _cursivas_ y saltos de línea. Devuelve
 * segmentos de TEXTO (se muestran con interpolación de Vue, siempre
 * escapados; nunca se genera HTML).
 */
export function segmentosTexto(texto: string): Segmento[][] {
    return texto.split('\n').map((linea) => {
        const segmentos: Segmento[] = [];
        const patron = /\*\*([^*\n]+)\*\*|_([^_\n]+)_/g;
        let ultimo = 0;
        let m: RegExpExecArray | null;

        while ((m = patron.exec(linea)) !== null) {
            if (m.index > ultimo) {
                segmentos.push({ texto: linea.slice(ultimo, m.index) });
            }

            segmentos.push(
                m[1] !== undefined
                    ? { texto: m[1], negrita: true }
                    : { texto: m[2], cursiva: true },
            );
            ultimo = m.index + m[0].length;
        }

        if (ultimo < linea.length) {
            segmentos.push({ texto: linea.slice(ultimo) });
        }

        return segmentos;
    });
}

/** Texto sin marcas de formato (atributos alt, títulos). */
export const textoPlano = (texto: string) =>
    texto.replace(/\*\*([^*]+)\*\*/g, '$1').replace(/_([^_]+)_/g, '$1');

const REF = {
    youtube: /^[A-Za-z0-9_-]{11}$/,
    vimeo: /^\d{5,12}$/,
    spotify: /^(track|album|playlist|episode|show|artist)\/[A-Za-z0-9]{22}$/,
    soundcloud: /^[a-z0-9_-]{1,100}(\/sets)?(\/[a-z0-9_-]{1,150})?$/,
    apple: /^[a-z]{2}\/(album|playlist|song)\/[a-z0-9-]{1,120}\/(pl\.)?[a-z0-9.-]{1,60}(\?i=\d{1,15})?$/,
};

/**
 * URL del reproductor a partir del proveedor y el identificador validados
 * (mismas plantillas que permite la CSP de la página pública). Nunca usa
 * una URL escrita por el usuario.
 */
export function urlInsertado(proveedor: string, ref: string): string | null {
    switch (proveedor) {
        case 'youtube':
            return REF.youtube.test(ref)
                ? `https://www.youtube-nocookie.com/embed/${ref}?rel=0`
                : null;
        case 'vimeo':
            return REF.vimeo.test(ref)
                ? `https://player.vimeo.com/video/${ref}?dnt=1`
                : null;
        case 'spotify':
            return REF.spotify.test(ref)
                ? `https://open.spotify.com/embed/${ref}`
                : null;
        case 'soundcloud':
            return REF.soundcloud.test(ref)
                ? `https://w.soundcloud.com/player/?url=${encodeURIComponent(`https://soundcloud.com/${ref}`)}&visual=false&show_comments=false`
                : null;
        case 'apple':
            return REF.apple.test(ref)
                ? `https://embed.music.apple.com/${ref}`
                : null;
        default:
            return null;
    }
}

export const urlMapa = (direccion: string) =>
    `https://www.google.com/maps?q=${encodeURIComponent(direccion)}&output=embed`;

export const altoMusica = (proveedor: string, ref: string) =>
    proveedor === 'spotify'
        ? ref.startsWith('track') || ref.startsWith('episode')
            ? 152
            : 352
        : proveedor === 'apple'
          ? ref.includes('?i=') || ref.includes('/song/')
              ? 175
              : 450
          : 166;

/**
 * Interpretación en el navegador de un enlace de video o música (solo para
 * la vista previa inmediata; el servidor vuelve a interpretarlo al guardar).
 */
export function interpretarInsertado(
    url: string,
    clase: 'video' | 'musica',
): { proveedor: string; ref: string } | null {
    let u: URL;

    try {
        u = new URL(
            /^https?:\/\//i.test(url.trim())
                ? url.trim()
                : `https://${url.trim()}`,
        );
    } catch {
        return null;
    }

    const host = u.hostname.replace(/^www\./, '').toLowerCase();
    const ruta = u.pathname;

    if (clase === 'video') {
        if (
            [
                'youtube.com',
                'm.youtube.com',
                'music.youtube.com',
                'youtube-nocookie.com',
            ].includes(host)
        ) {
            const id =
                ruta === '/watch'
                    ? (u.searchParams.get('v') ?? '')
                    : (/^\/(embed|shorts|live|v)\/([^/]+)/.exec(ruta)?.[2] ??
                      '');

            return REF.youtube.test(id)
                ? { proveedor: 'youtube', ref: id }
                : null;
        }

        if (host === 'youtu.be') {
            const id = ruta.slice(1);

            return REF.youtube.test(id)
                ? { proveedor: 'youtube', ref: id }
                : null;
        }

        if (host === 'vimeo.com' || host === 'player.vimeo.com') {
            const id = /^\/(?:video\/)?(\d{5,12})(?:\/|$)/.exec(ruta)?.[1];

            return id ? { proveedor: 'vimeo', ref: id } : null;
        }

        return null;
    }

    if (host === 'open.spotify.com') {
        const m =
            /^\/(track|album|playlist|episode|show|artist)\/([A-Za-z0-9]{22})\/?$/.exec(
                ruta.replace(/^\/(intl-[a-z]{2}(-[a-z]{2})?|embed)\//i, '/'),
            );

        return m ? { proveedor: 'spotify', ref: `${m[1]}/${m[2]}` } : null;
    }

    if (host === 'soundcloud.com' || host === 'm.soundcloud.com') {
        const ref = ruta.toLowerCase().replace(/\/$/, '').slice(1);

        return REF.soundcloud.test(ref)
            ? { proveedor: 'soundcloud', ref }
            : null;
    }

    if (host === 'music.apple.com') {
        const m =
            /^\/([a-z]{2})\/(album|playlist|song)\/([^/]+)\/((?:pl\.)?[a-z0-9.-]{1,60})$/i.exec(
                ruta.replace(/\/$/, ''),
            );

        if (!m) {
            return null;
        }

        const slug =
            decodeURIComponent(m[3])
                .toLowerCase()
                .replace(/[^a-z0-9-]/g, '') || 'x';
        const pista = u.searchParams.get('i');
        const ref = `${m[1].toLowerCase()}/${m[2]}/${slug}/${m[4]}${pista && /^\d{1,15}$/.test(pista) ? `?i=${pista}` : ''}`;

        return REF.apple.test(ref) ? { proveedor: 'apple', ref } : null;
    }

    return null;
}
