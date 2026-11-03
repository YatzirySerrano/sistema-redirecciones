import type { FunctionalComponent } from 'vue';
import { h } from 'vue';

/**
 * Glifos lineales propios (24 × 24, trazo 2) para representar cada red
 * social. Son dibujos simplificados de DASTI con el mismo estilo que los
 * íconos de Lucide: no reproducen los logotipos oficiales.
 */
type Trazo =
    | ['path', string]
    | ['circle', number, number, number, boolean?]
    | ['rect', number, number, number, number, number];

const crear = (nombre: string, trazos: Trazo[]): FunctionalComponent => {
    const componente: FunctionalComponent = (_, { attrs }) =>
        h(
            'svg',
            {
                xmlns: 'http://www.w3.org/2000/svg',
                viewBox: '0 0 24 24',
                fill: 'none',
                stroke: 'currentColor',
                'stroke-width': 2,
                'stroke-linecap': 'round',
                'stroke-linejoin': 'round',
                'aria-hidden': 'true',
                ...attrs,
            },
            trazos.map((t) => {
                if (t[0] === 'path') {
                    return h('path', { d: t[1] });
                }

                if (t[0] === 'circle') {
                    return h('circle', {
                        cx: t[1],
                        cy: t[2],
                        r: t[3],
                        fill: t[4] ? 'currentColor' : 'none',
                        stroke: t[4] ? 'none' : undefined,
                    });
                }

                return h('rect', {
                    x: t[1],
                    y: t[2],
                    width: t[3],
                    height: t[4],
                    rx: t[5],
                });
            }),
        );
    componente.displayName = nombre;

    return componente;
};

export const ICONOS_REDES: Record<string, FunctionalComponent> = {
    instagram: crear('Instagram', [
        ['rect', 2.5, 2.5, 19, 19, 5.5],
        ['circle', 12, 12, 4.2],
        ['circle', 17.4, 6.6, 1.1, true],
    ]),
    facebook: crear('Facebook', [
        [
            'path',
            'M14.5 3H13a4 4 0 0 0-4 4v3H6.5v3.5H9V21h3.5v-7.5h2.8l.6-3.5h-3.4V7.6c0-.5.4-1 1-1h1.9z',
        ],
    ]),
    tiktok: crear('TikTok', [
        ['path', 'M13.5 3v11.6a3.6 3.6 0 1 1-3.6-3.6'],
        ['path', 'M13.5 3.2c.5 2.6 2.4 4.4 5.2 4.6'],
    ]),
    x: crear('X', [
        ['path', 'M4 4l6.8 9.2L4.4 20'],
        ['path', 'M19.6 4l-6.3 6.8'],
        ['path', 'M4 4h3.6L20 20h-3.6z'],
    ]),
    youtube: crear('YouTube', [
        ['rect', 2.5, 5, 19, 14, 4.5],
        ['path', 'M10 9.2v5.6l4.8-2.8z'],
    ]),
    linkedin: crear('LinkedIn', [
        ['rect', 3, 3, 18, 18, 3.5],
        ['path', 'M8 10.5V16'],
        ['circle', 8, 7.4, 1.05, true],
        ['path', 'M12 16v-5.5'],
        ['path', 'M12 13.2a2.4 2.4 0 0 1 4.8 0V16'],
    ]),
    whatsapp: crear('WhatsApp', [
        ['path', 'M3.6 20.4l1.2-3.8A8.6 8.6 0 1 1 8 19.6z'],
        [
            'path',
            'M9.2 8.6c-.2 2.8 3.2 6.3 6.2 6.2l.9-1.4-1.9-1-1 .7a4.6 4.6 0 0 1-2.4-2.4l.7-1-1-1.9z',
        ],
    ]),
    telegram: crear('Telegram', [
        ['path', 'M21 4.2L2.8 11.3l5.7 2.1 2.2 6.4 3.2-3.8 4.7 3.6z'],
        ['path', 'M8.5 13.4l9.4-6.8'],
    ]),
    pinterest: crear('Pinterest', [
        ['circle', 12, 12, 9.2],
        ['path', 'M11 20.8l1.6-7.4'],
        ['path', 'M9.8 13.6a3.2 3.2 0 1 1 3.4 1.3c-1 .2-1.6-.3-1.6-.3'],
    ]),
    threads: crear('Threads', [
        ['circle', 12, 12, 3.6],
        ['path', 'M15.6 8.4v4.9a2.7 2.7 0 0 0 5.4 0V12a9 9 0 1 0-3.6 7.2'],
    ]),
    spotify: crear('Spotify', [
        ['circle', 12, 12, 9.4],
        ['path', 'M7.2 9.4c3.4-1 7.1-.7 9.9.9'],
        ['path', 'M7.8 12.6c2.9-.8 5.8-.5 8.1.8'],
        ['path', 'M8.4 15.7c2.2-.6 4.3-.4 6.2.6'],
    ]),
    sitio: crear('Sitio web', [
        ['circle', 12, 12, 9.4],
        ['path', 'M2.6 12h18.8'],
        ['path', 'M12 2.6a14.4 14.4 0 0 1 0 18.8a14.4 14.4 0 0 1 0-18.8z'],
    ]),
};
