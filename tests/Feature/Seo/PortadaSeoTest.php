<?php

namespace Tests\Feature\Seo;

use App\Http\Controllers\BienvenidaController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Portada pública, metadatos SEO/Open Graph, robots.txt, sitemap e
 * identidad (favicon e imagen para compartir).
 */
class PortadaSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_la_portada_es_publica_e_indexable_con_metadatos_completos(): void
    {
        $respuesta = $this->get('/')->assertOk()
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina
                ->component('Bienvenida')
                ->has('preguntas', count(BienvenidaController::PREGUNTAS))
                ->where('contacto.correo', null)
                ->where('contacto.whatsapp', null));

        $respuesta
            ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false)
            ->assertSee('<link rel="canonical" href="https://redireccion.test/">', false)
            ->assertSee('<meta name="description" content="DASTI: redirecciones dinámicas', false)
            ->assertSee('códigos QR dinámicos', false)
            ->assertSee('<meta property="og:image" content="https://redireccion.test/og/dasti-og.png">', false)
            ->assertSee('<meta property="og:image:width" content="1200">', false)
            ->assertSee('<meta property="og:image:height" content="630">', false)
            ->assertSee('<meta property="og:image:alt"', false)
            ->assertSee('<meta property="og:locale" content="es_MX">', false)
            ->assertSee('<meta property="og:site_name" content="DASTI">', false)
            ->assertSee('<meta property="og:url" content="https://redireccion.test/">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
            ->assertSee('name="theme-color"', false)
            ->assertSee('<title>DASTI · Redirecciones QR y NFC dinámicas</title>', false)
            ->assertDontSee('favicon.svg', false);
    }

    public function test_la_portada_incluye_datos_estructurados(): void
    {
        $html = (string) $this->get('/')->getContent();

        preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $coincidencia);
        $this->assertNotEmpty($coincidencia, 'Falta el JSON-LD.');

        $datos = json_decode($coincidencia[1], true, flags: JSON_THROW_ON_ERROR);
        $tipos = array_column($datos['@graph'], '@type');

        foreach (['Organization', 'WebSite', 'Service', 'FAQPage'] as $tipo) {
            $this->assertContains($tipo, $tipos);
        }

        $faq = collect($datos['@graph'])->firstWhere('@type', 'FAQPage');
        $this->assertCount(count(BienvenidaController::PREGUNTAS), $faq['mainEntity']);
        $this->assertSame(BienvenidaController::PREGUNTAS[0]['pregunta'], $faq['mainEntity'][0]['name']);
    }

    public function test_el_boton_contactar_solo_aparece_con_datos_configurados(): void
    {
        config(['dasti.sitio.correo_contacto' => 'hola@dasti.test', 'dasti.sitio.whatsapp_contacto' => '+52 1 55 1234 5678']);

        $this->get('/')->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('contacto.correo', 'hola@dasti.test')
            ->where('contacto.whatsapp', 'https://wa.me/5215512345678'));
    }

    public function test_con_sesion_la_raiz_lleva_al_panel(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('inicio'));
    }

    public function test_el_inicio_de_sesion_es_indexable(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false)
            ->assertSee('<link rel="canonical" href="https://redireccion.test/login">', false)
            ->assertSee('<meta property="og:image" content="https://redireccion.test/og/dasti-og.png">', false);
    }

    public function test_las_paginas_internas_no_se_indexan(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.panel'))->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertDontSee('rel="canonical"', false)
            ->assertDontSee('application/ld+json', false);

        auth()->logout();
        $this->get(route('password.request'))->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_robots_txt_bloquea_el_panel_pero_no_los_enlaces_publicos(): void
    {
        $robots = (string) file_get_contents(public_path('robots.txt'));

        $this->assertMatchesRegularExpression('#^Allow: /$#m', $robots);
        foreach (['/admin/', '/panel/', '/inicio', '/settings/', '/productos/'] as $ruta) {
            $this->assertStringContainsString("Disallow: {$ruta}", $robots);
        }
        // Los robots de vista previa respetan robots.txt: nunca bloquear estos prefijos.
        foreach (['/r/', '/r', '/l/', '/f/', '/'] as $ruta) {
            $this->assertDoesNotMatchRegularExpression('#^Disallow: '.preg_quote($ruta, '#').'$#m', $robots);
        }
        $this->assertMatchesRegularExpression('#^Sitemap: https://[^\s]+/sitemap\.xml$#m', $robots);
    }

    public function test_el_sitemap_lista_solo_las_paginas_publicas_de_dasti(): void
    {
        $respuesta = $this->get('/sitemap.xml')->assertOk();

        $this->assertStringStartsWith('application/xml', (string) $respuesta->headers->get('Content-Type'));
        $xml = simplexml_load_string((string) $respuesta->getContent());
        $this->assertNotFalse($xml);

        $urls = [];
        foreach ($xml->url as $url) {
            $urls[] = (string) $url->loc;
        }

        $this->assertSame(['https://redireccion.test/', 'https://redireccion.test/login'], $urls);
    }

    public function test_los_iconos_y_la_imagen_para_compartir_tienen_el_tamano_correcto(): void
    {
        $esperados = [
            'iconos/favicon-32.png' => [32, 32],
            'iconos/apple-touch-icon.png' => [180, 180],
            'apple-touch-icon.png' => [180, 180],
            'iconos/icono-192.png' => [192, 192],
            'iconos/icono-512.png' => [512, 512],
            'iconos/icono-maskable-512.png' => [512, 512],
            'og/dasti-og.png' => [1200, 630],
            'marca/dasti-isotipo.png' => [256, 256],
        ];

        foreach ($esperados as $archivo => [$ancho, $alto]) {
            $info = getimagesize(public_path($archivo));
            $this->assertNotFalse($info, "No es una imagen: {$archivo}");
            $this->assertSame([$ancho, $alto], [$info[0], $info[1]], $archivo);
            $this->assertSame('image/png', $info['mime'], $archivo);
        }

        // favicon.ico con varios tamaños (16, 32 y 48).
        $ico = (string) file_get_contents(public_path('favicon.ico'));
        $cabecera = unpack('vreservado/vtipo/vcantidad', substr($ico, 0, 6));
        $this->assertSame(1, $cabecera['tipo']);
        $tamanos = [];
        for ($i = 0; $i < $cabecera['cantidad']; $i++) {
            $tamanos[] = ord($ico[6 + 16 * $i]);
        }
        $this->assertSame([16, 32, 48], $tamanos);
    }
}
