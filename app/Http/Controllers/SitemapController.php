<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Mapa del sitio (/sitemap.xml): solo las páginas públicas de DASTI
 * (portada e inicio de sesión).
 *
 * Las Páginas de enlaces de los negocios NO se incluyen aunque permitan
 * indexarse: pertenecen a cada negocio, se publican sin marca de DASTI,
 * pueden vivir en su propio dominio (con su propia URL canónica) y su
 * indexación depende del ajuste de cada negocio. Los buscadores las
 * descubren desde los enlaces que el negocio comparte.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $base = rtrim((string) config('app.url'), '/');
        $fecha = date('Y-m-d', (int) (@filemtime(resource_path('js/pages/Bienvenida.vue')) ?: time()));

        $urls = [
            ['loc' => $base.'/', 'prioridad' => '1.0', 'frecuencia' => 'weekly'],
            ['loc' => $base.'/login', 'prioridad' => '0.5', 'frecuencia' => 'yearly'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= '  <url>'
                .'<loc>'.e($url['loc']).'</loc>'
                .'<lastmod>'.$fecha.'</lastmod>'
                .'<changefreq>'.$url['frecuencia'].'</changefreq>'
                .'<priority>'.$url['prioridad'].'</priority>'
                .'</url>'."\n";
        }

        $xml .= '</urlset>'."\n";

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
