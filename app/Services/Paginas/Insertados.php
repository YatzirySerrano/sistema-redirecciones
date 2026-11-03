<?php

namespace App\Services\Paginas;

/**
 * Contenido insertado de proveedores permitidos. Nunca se guarda una URL de
 * iframe escrita por el usuario: se extrae el identificador del proveedor y
 * la interfaz arma la dirección del reproductor con una plantilla fija
 * (resources/js/components/paginas/insertados.ts). La CSP de la página
 * pública solo admite estos orígenes en frame-src.
 */
final class Insertados
{
    /** Orígenes de los reproductores (frame-src de la página pública). */
    public const ORIGENES = [
        'https://www.youtube-nocookie.com',
        'https://player.vimeo.com',
        'https://open.spotify.com',
        'https://w.soundcloud.com',
        'https://embed.music.apple.com',
        'https://www.google.com',
    ];

    /**
     * @return array{url: string, proveedor: string, ref: string}|null
     */
    public static function video(string $url): ?array
    {
        $partes = self::partes($url);

        if ($partes === null) {
            return null;
        }

        [$host, $ruta, $consulta] = $partes;

        if (in_array($host, ['youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtube-nocookie.com'], true)) {
            $id = match (true) {
                $ruta === '/watch' => (string) ($consulta['v'] ?? ''),
                (bool) preg_match('#^/(embed|shorts|live|v)/([^/]+)#', $ruta, $m) => $m[2],
                default => '',
            };
        } elseif ($host === 'youtu.be') {
            $id = ltrim($ruta, '/');
        } elseif ($host === 'vimeo.com' || $host === 'player.vimeo.com') {
            if (preg_match('#^/(?:video/)?(\d{5,12})(?:/|$)#', $ruta, $m)) {
                return ['url' => 'https://vimeo.com/'.$m[1], 'proveedor' => 'vimeo', 'ref' => $m[1]];
            }

            return null;
        } else {
            return null;
        }

        if (! preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) {
            return null;
        }

        return ['url' => 'https://www.youtube.com/watch?v='.$id, 'proveedor' => 'youtube', 'ref' => $id];
    }

    /**
     * @return array{url: string, proveedor: string, ref: string}|null
     */
    public static function musica(string $url): ?array
    {
        $partes = self::partes($url);

        if ($partes === null) {
            return null;
        }

        [$host, $ruta, $consulta] = $partes;

        if ($host === 'open.spotify.com') {
            $ruta = (string) preg_replace('#^/(intl-[a-z]{2}(-[a-z]{2})?|embed)/#i', '/', $ruta);

            if (preg_match('#^/(track|album|playlist|episode|show|artist)/([A-Za-z0-9]{22})/?$#', $ruta, $m)) {
                return ['url' => "https://open.spotify.com/{$m[1]}/{$m[2]}", 'proveedor' => 'spotify', 'ref' => "{$m[1]}/{$m[2]}"];
            }

            return null;
        }

        if ($host === 'soundcloud.com' || $host === 'm.soundcloud.com') {
            $ruta = strtolower(rtrim($ruta, '/'));

            if (preg_match('#^/([a-z0-9_-]{1,100})(/sets)?/([a-z0-9_-]{1,150})$#', $ruta, $m) || preg_match('#^/([a-z0-9_-]{1,100})$#', $ruta, $m)) {
                $ref = ltrim($ruta, '/');

                return ['url' => 'https://soundcloud.com/'.$ref, 'proveedor' => 'soundcloud', 'ref' => $ref];
            }

            return null;
        }

        if ($host === 'music.apple.com' || $host === 'embed.music.apple.com') {
            if (preg_match('#^/([a-z]{2})/(album|playlist|song)/([a-z0-9%_-]{1,120})/((?:pl\.)?[a-z0-9.-]{1,60})$#i', rtrim($ruta, '/'), $m)) {
                $slug = strtolower((string) preg_replace('/[^a-z0-9-]/i', '', rawurldecode($m[3]))) ?: 'x';
                $ref = strtolower($m[1]).'/'.$m[2].'/'.$slug.'/'.$m[4];
                $pista = isset($consulta['i']) && preg_match('/^\d{1,15}$/', (string) $consulta['i']) ? (string) $consulta['i'] : '';

                if ($pista !== '') {
                    $ref .= '?i='.$pista;
                }

                return ['url' => 'https://music.apple.com/'.$ref, 'proveedor' => 'apple', 'ref' => $ref];
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string, 2: array<int|string, mixed>}|null
     */
    private static function partes(string $url): ?array
    {
        $url = trim($url);

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $partes = parse_url($url);

        if ($partes === false || ! isset($partes['host']) || isset($partes['user']) || isset($partes['port'])) {
            return null;
        }

        $host = (string) preg_replace('/^www\./', '', strtolower($partes['host']));
        parse_str((string) ($partes['query'] ?? ''), $consulta);

        return [$host, (string) ($partes['path'] ?? '/'), $consulta];
    }
}
