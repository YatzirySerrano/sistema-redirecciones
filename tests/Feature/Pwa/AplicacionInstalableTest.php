<?php

namespace Tests\Feature\Pwa;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PWA: manifiesto válido, íconos presentes y Service Worker que nunca
 * guarda información privada ni redirecciones dinámicas.
 */
class AplicacionInstalableTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_manifiesto_es_valido_e_instalable(): void
    {
        $manifiesto = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('DASTI', $manifiesto['short_name']);
        $this->assertSame('standalone', $manifiesto['display']);
        $this->assertSame('/', $manifiesto['scope']);
        $this->assertStringStartsWith('/', $manifiesto['start_url']);

        $tamanos = array_column($manifiesto['icons'], 'sizes');
        $this->assertContains('192x192', $tamanos);
        $this->assertContains('512x512', $tamanos);
        $this->assertContains('maskable', array_column($manifiesto['icons'], 'purpose'));

        foreach ($manifiesto['icons'] as $icono) {
            $this->assertFileExists(public_path(ltrim($icono['src'], '/')));
        }
    }

    public function test_las_paginas_enlazan_el_manifiesto(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
            ->assertSee('name="theme-color"', false);
    }

    public function test_el_service_worker_no_guarda_datos_privados(): void
    {
        $sw = (string) file_get_contents(public_path('sw.js'));

        // Solo se interceptan GET del mismo origen.
        $this->assertStringContainsString("request.method !== 'GET'", $sw);
        // Nunca: redirecciones públicas, Fidelidad pública, Stripe, sesión.
        foreach (["'/r/'", "'/f/'", "'/mi-fidelidad'", "'/stripe'", "'/login'", "'/logout'"] as $prefijo) {
            $this->assertStringContainsString($prefijo, $sw);
        }
        // Las navegaciones van a la red; sin conexión, página informativa.
        $this->assertStringContainsString("request.mode === 'navigate'", $sw);
        $this->assertStringContainsString('fetch(request).catch(() => caches.match(OFFLINE))', $sw);
        // Solo se guardan recursos compilados públicos.
        $this->assertSame(1, substr_count($sw, 'cache.put('));
        $this->assertStringContainsString("url.pathname.startsWith('/build/assets/')", $sw);
    }

    public function test_la_pagina_sin_conexion_no_contiene_datos_de_cuenta(): void
    {
        $html = (string) file_get_contents(public_path('offline.html'));

        $this->assertStringContainsString('No hay conexión a internet', $html);
        $this->assertStringContainsString('Las visitas y canjes de Fidelidad requieren conexión.', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_las_redirecciones_siguen_sin_cache_para_reflejar_el_destino_vigente(): void
    {
        $producto = Producto::factory()->create(['url_destino' => 'https://www.example.org/antes']);
        $this->get('/r/'.$producto->codigo)->assertRedirect('https://www.example.org/antes')
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');

        $producto->forceFill(['url_destino' => 'https://www.example.org/despues'])->save();
        $this->get('/r/'.$producto->codigo)->assertRedirect('https://www.example.org/despues');
    }

    public function test_las_respuestas_del_panel_no_son_cacheables_por_intermediarios(): void
    {
        $admin = User::factory()->create();
        $cache = (string) $this->actingAs($admin)->get(route('admin.panel'))->headers->get('Cache-Control');

        $this->assertStringContainsString('private', $cache);
    }
}
