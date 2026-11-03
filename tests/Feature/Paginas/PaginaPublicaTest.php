<?php

namespace Tests\Feature\Paginas;

use App\Enums\EstadoContratacion;
use App\Enums\EstadoCuenta;
use App\Models\Contratacion;
use App\Models\Paginas\PaginaEnlaces;
use App\Services\Paginas\GestorPaginas;
use App\Services\Servicios\AccesoServicios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\PreparaPaginas;
use Tests\TestCase;

class PaginaPublicaTest extends TestCase
{
    use PreparaPaginas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(string $html): array
    {
        $this->assertSame(1, preg_match('#<script type="application/json" id="pagina-datos">(.*?)</script>#s', $html, $m));

        return json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_pagina_publicada_con_metadatos_seguridad_y_sin_marca_dasti(): void
    {
        $negocio = $this->negocioConPaginas();
        $portada = $this->imagen($negocio);
        $enlace = $this->enlace('Reserva <b>ya</b>', 'https://cafe-aurora.mx/reservar');
        $pagina = $this->publicar($negocio, $this->documento([$enlace], [
            'perfil' => ['nombre' => 'Café Aurora', 'descripcion' => 'Café </script><script>alert(1)</script> de especialidad', 'portada' => $portada->uuid],
            'ajustes' => ['seo_descripcion' => 'El mejor café de la colonia'],
        ]));

        $respuesta = $this->get('/l/'.$pagina->slug)->assertOk();
        $html = (string) $respuesta->getContent();

        $respuesta->assertHeader('X-Content-Type-Options', 'nosniff');
        $respuesta->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertNotNull($respuesta->headers->get('Permissions-Policy'));
        $csp = (string) $respuesta->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        $this->assertStringContainsString('frame-src https://www.youtube-nocookie.com', $csp);
        $this->assertStringContainsString('font-src https://fonts.bunny.net', $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertEmpty($respuesta->headers->getCookies(), 'La página pública no debe crear cookies.');

        $this->assertStringContainsString('<title>Café Aurora</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="El mejor café de la colonia">', $html);
        $this->assertStringContainsString('<meta property="og:image" content="https://redireccion.test/medios/'.$portada->uuid.'.'.$portada->extension.'">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://redireccion.test/l/'.$pagina->slug.'">', $html);
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringNotContainsString('DASTI', $html);
        $this->assertStringNotContainsString('dasti', strtolower(str_replace('_dasti', '', $html)));
        $this->assertStringNotContainsString('favicon.ico', $html);

        // Respaldo sin JavaScript con el enlace de seguimiento.
        $this->assertStringContainsString('href="/l/'.$pagina->slug.'/ir/'.$enlace['id'].'"', $html);

        $datos = $this->datos($html);
        $bloque = $datos['documento']['paginas'][0]['bloques'][0];
        $this->assertSame('/l/'.$pagina->slug.'/ir/'.$enlace['id'], $bloque['href']);
        $this->assertArrayNotHasKey('url', $bloque, 'El destino no se expone: solo la ruta de seguimiento.');
    }

    public function test_la_vista_previa_y_la_pagina_publica_usan_el_mismo_documento(): void
    {
        $negocio = $this->negocioConPaginas();
        $pagina = $this->publicar($negocio, $this->documento([
            $this->enlace('Uno'),
            ['tipo' => 'texto', 'texto' => 'Hola **mundo**'],
        ]));

        $this->assertSame($pagina->borrador, $pagina->publicado);

        $datos = $this->datos((string) $this->get('/l/'.$pagina->slug)->getContent());
        $publico = $datos['documento']['paginas'][0]['bloques'];
        $this->assertSame(array_column($pagina->publicado['paginas'][0]['bloques'], 'id'), array_column($publico, 'id'));
        $this->assertSame($pagina->publicado['tema'], $datos['documento']['tema']);
        $this->assertSame('Hola **mundo**', $publico[1]['texto']);
    }

    public function test_no_publicada_despublicada_y_servicio_suspendido_muestran_pagina_neutra(): void
    {
        $negocio = $this->negocioConPaginas();
        $pagina = app(GestorPaginas::class)->paraNegocio($negocio);

        $this->get('/l/'.$pagina->slug)->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertDontSee('DASTI');
        $this->get('/l/no-existe-esta')->assertNotFound();

        $pagina = $this->publicar($negocio, $this->documento([$this->enlace()]));
        $this->get('/l/'.$pagina->slug)->assertOk();

        app(GestorPaginas::class)->despublicar($pagina, $this->propietario($negocio));
        $this->get('/l/'.$pagina->slug)->assertNotFound();

        $pagina = $this->publicar($negocio, $this->documento([$this->enlace()]));
        Contratacion::query()->where('negocio_id', $negocio->id)->update(['estado' => EstadoContratacion::Suspendida]);
        app(AccesoServicios::class)->olvidar();
        $this->get('/l/'.$pagina->slug)->assertStatus(410)->assertSee('no está disponible');
        $this->assertNotNull(PaginaEnlaces::query()->find($pagina->id)?->publicado, 'La configuración se conserva.');

        Contratacion::query()->where('negocio_id', $negocio->id)->update(['estado' => EstadoContratacion::Activa]);
        app(AccesoServicios::class)->olvidar();
        $negocio->forceFill(['estado' => EstadoCuenta::Suspendido])->save();
        $this->get('/l/'.$pagina->slug)->assertStatus(410);
    }

    public function test_elementos_programados_solo_se_muestran_dentro_de_su_periodo(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 10)->setTime(12, 0));
        $negocio = $this->negocioConPaginas();
        $futuro = $this->enlace('Promo navideña', 'https://a.mx', ['visible_desde' => '2026-12-01T00:00']);
        $vencido = $this->enlace('Buen fin', 'https://b.mx', ['visible_hasta' => '2026-11-05T00:00']);
        $vigente = $this->enlace('Siempre', 'https://c.mx', ['visible_desde' => '2026-11-01T00:00', 'visible_hasta' => '2026-11-30T00:00']);
        $oculto = $this->enlace('Oculto', 'https://d.mx', ['oculto' => true]);
        $pagina = $this->publicar($negocio, $this->documento([$futuro, $vencido, $vigente, $oculto]));

        $ids = fn () => array_column($this->datos((string) $this->get('/l/'.$pagina->slug)->getContent())['documento']['paginas'][0]['bloques'], 'id');

        $this->assertSame([$vigente['id']], $ids());
        $this->get("/l/{$pagina->slug}/ir/{$futuro['id']}")->assertNotFound();
        $this->get("/l/{$pagina->slug}/ir/{$oculto['id']}")->assertNotFound();

        $this->travelTo(now()->setDate(2026, 12, 2));
        $this->assertSame([$futuro['id']], $ids());
        $this->get("/l/{$pagina->slug}/ir/{$futuro['id']}")->assertRedirect('https://a.mx');
    }

    public function test_clic_redirige_solo_al_destino_publicado_actual(): void
    {
        $negocio = $this->negocioConPaginas();
        $enlace = $this->enlace('Menú', 'https://cafe-aurora.mx/menu');
        $pagina = $this->publicar($negocio, $this->documento([$enlace]));

        $this->get("/l/{$pagina->slug}/ir/{$enlace['id']}?url=https://evil.example")
            ->assertStatus(302)
            ->assertRedirect('https://cafe-aurora.mx/menu')
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');

        // Un cambio solo en el borrador no altera el destino público.
        $usuario = $this->propietario($negocio);
        $gestor = app(GestorPaginas::class);
        $doc = $pagina->borrador;
        $doc['paginas'][0]['bloques'][0]['url'] = 'https://cafe-aurora.mx/nuevo';
        $pagina = $gestor->guardarBorrador($pagina, $doc, $pagina->version, $usuario);
        $this->get("/l/{$pagina->slug}/ir/{$enlace['id']}")->assertRedirect('https://cafe-aurora.mx/menu');

        $gestor->publicar($pagina, $pagina->version, $usuario);
        $this->get("/l/{$pagina->slug}/ir/{$enlace['id']}")->assertRedirect('https://cafe-aurora.mx/nuevo');

        $this->get("/l/{$pagina->slug}/ir/".Str::uuid())->assertNotFound();
    }

    public function test_telefono_y_correo_se_abren_directo_y_registran_el_clic_por_post(): void
    {
        $negocio = $this->negocioConPaginas();
        $tel = ['id' => (string) Str::uuid(), 'tipo' => 'telefono', 'titulo' => 'Llamar', 'telefono' => '5512345678'];
        $pagina = $this->publicar($negocio, $this->documento([$tel]));

        $bloque = $this->datos((string) $this->get('/l/'.$pagina->slug)->getContent())['documento']['paginas'][0]['bloques'][0];
        $this->assertSame('tel:+525512345678', $bloque['href']);
        $this->assertSame("/l/{$pagina->slug}/ir/{$tel['id']}", $bloque['rastreo']);

        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)')
            ->post("/l/{$pagina->slug}/ir/{$tel['id']}")->assertNoContent();
        $this->assertDatabaseHas('paginas_enlaces_diarios', ['pagina_id' => $pagina->id, 'tipo' => 'clic', 'bloque_id' => $tel['id'], 'total' => 1]);
    }

    public function test_subpaginas_multiples(): void
    {
        $negocio = $this->negocioConPaginas();
        $menu = (string) Str::uuid();
        $pagina = $this->publicar($negocio, ['perfil' => ['nombre' => 'Café'], 'paginas' => [
            ['titulo' => 'Inicio', 'bloques' => [['id' => $irMenu = (string) Str::uuid(), 'tipo' => 'subpagina', 'titulo' => 'Ver menú', 'pagina_id' => $menu]]],
            ['id' => $menu, 'titulo' => 'Menú', 'slug' => 'menu', 'descripcion' => 'Nuestro menú', 'bloques' => [$this->enlace('Postres')]],
            ['titulo' => 'Eventos', 'slug' => 'eventos', 'bloques' => [$this->enlace('Agenda')]],
        ]]);

        $html = (string) $this->get("/l/{$pagina->slug}/menu")->assertOk()->getContent();
        $this->assertStringContainsString('<title>Menú · Café</title>', $html);
        $this->assertStringContainsString('href="https://redireccion.test/l/'.$pagina->slug.'/menu"', $html);
        $datos = $this->datos($html);
        $this->assertSame('menu', $datos['pagina']);
        $this->assertSame('Postres', collect($datos['documento']['paginas'])->firstWhere('slug', 'menu')['bloques'][0]['titulo']);
        $this->assertSame([], collect($datos['documento']['paginas'])->firstWhere('slug', 'eventos')['bloques'], 'Solo se envía el contenido de la página actual.');

        $this->get("/l/{$pagina->slug}/eventos")->assertOk();
        $this->get("/l/{$pagina->slug}/no-existe")->assertNotFound();
        $this->get("/l/{$pagina->slug}/inicio")->assertNotFound();
        $this->get("/l/{$pagina->slug}/ir/{$irMenu}")->assertRedirect("/l/{$pagina->slug}/menu");
    }

    public function test_slug_reservado_formato_alias_y_antisuplantacion(): void
    {
        $negocio = $this->negocioConPaginas();
        $usuario = $this->propietario($negocio);
        $gestor = app(GestorPaginas::class);
        $pagina = $this->publicar($negocio, $this->documento([$this->enlace()]), $usuario);
        $original = $pagina->slug;

        foreach (['admin', 'dasti', 'soporte-dasti', 'login', 'ab', 'Mi Café', '-cafe', 'cafe--x', '12345', str_repeat('a', 41)] as $malo) {
            try {
                $gestor->cambiarSlug($pagina, $malo, $usuario);
                $this->fail("Debió rechazarse «{$malo}»");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('slug', $e->errors());
            }
        }

        $pagina = $gestor->cambiarSlug($pagina, 'cafe-aurora-centro', $usuario);
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'pagina.direccion_cambiada', 'negocio_id' => $negocio->id]);

        // La dirección anterior redirige (301) y queda reservada.
        $this->get("/l/{$original}")->assertStatus(301)->assertRedirect('/l/cafe-aurora-centro');
        $this->get("/l/{$original}/qr.svg")->assertStatus(301);

        $otro = $this->negocioConPaginas();
        $paginaOtro = $gestor->paraNegocio($otro);

        try {
            $gestor->cambiarSlug($paginaOtro, $original, $this->propietario($otro));
            $this->fail('Otro negocio no debe poder ocupar una dirección anterior.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('en uso', $e->errors()['slug'][0]);
        }

        // Tiempo mínimo entre cambios.
        try {
            $gestor->cambiarSlug($pagina, 'cafe-aurora-sur', $usuario);
            $this->fail('Debe respetar el tiempo entre cambios.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('horas', $e->errors()['slug'][0]);
        }
    }

    public function test_cada_negocio_recibe_una_direccion_derivada_de_su_nombre(): void
    {
        $negocio = $this->negocioConPaginas();
        $negocio->forceFill(['nombre_comercial' => 'Panadería La Espiga'])->save();
        $this->assertSame('panaderia-la-espiga', app(GestorPaginas::class)->paraNegocio($negocio)->slug);

        $otro = $this->negocioConPaginas();
        $otro->forceFill(['nombre_comercial' => 'Panadería La Espiga'])->save();
        $this->assertSame('panaderia-la-espiga-2', app(GestorPaginas::class)->paraNegocio($otro)->slug);

        $dasti = $this->negocioConPaginas();
        $dasti->forceFill(['nombre_comercial' => 'Admin'])->save();
        $this->assertStringStartsWith('negocio-', app(GestorPaginas::class)->paraNegocio($dasti)->slug);
    }

    public function test_qr_publico_de_la_pagina(): void
    {
        $negocio = $this->negocioConPaginas();
        $pagina = $this->publicar($negocio, $this->documento([['tipo' => 'qr', 'titulo' => 'Escanéame']]));

        $respuesta = $this->get("/l/{$pagina->slug}/qr.svg")->assertOk();
        $this->assertStringContainsString('image/svg+xml', (string) $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', (string) $respuesta->getContent());
    }
}
