<?php

namespace Tests\Feature\Paginas;

use App\Models\CambioDestino;
use App\Models\EventoAcceso;
use App\Models\Producto;
use App\Models\User;
use App\Services\Paginas\GestorPaginas;
use App\Services\Redirecciones\ValidadorUrlDestino;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PreparaPaginas;
use Tests\TestCase;

/**
 * La Página de enlaces como destino de QR/NFC existentes, mediante el flujo
 * autorizado de cambio de destino. La excepción al bloqueo del dominio
 * propio es estrecha y no afecta a los demás productos.
 */
class DestinoProductoTest extends TestCase
{
    use PreparaPaginas, RefreshDatabase;

    private const UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    public function test_excepcion_estrecha_solo_para_paginas_publicadas(): void
    {
        $negocio = $this->negocioConPaginas();
        $pagina = app(GestorPaginas::class)->paraNegocio($negocio);
        $validador = app(ValidadorUrlDestino::class);
        $url = 'https://redireccion.test/l/'.$pagina->slug;

        $this->assertNotNull($validador->error($url), 'Una página sin publicar no es un destino válido.');

        $pagina = $this->publicar($negocio, ['perfil' => ['nombre' => 'Café'], 'paginas' => [
            ['titulo' => 'Inicio', 'bloques' => [$this->enlace()]],
            ['titulo' => 'Menú', 'slug' => 'menu', 'bloques' => []],
        ]]);

        $this->assertNull($validador->error($url));
        $this->assertNull($validador->error($url.'/menu'));

        foreach ([
            'https://redireccion.test/r/abcdefghjk',
            'https://redireccion.test/admin',
            'https://redireccion.test/panel/paginas',
            'https://redireccion.test/l/'.$pagina->slug.'?x=https://evil.example',
            'https://redireccion.test/l/'.$pagina->slug.'/ir/'.$pagina->publicado['paginas'][0]['bloques'][0]['id'],
            'https://redireccion.test/l/otro-negocio-inexistente',
            'https://sub.redireccion.test/l/'.$pagina->slug,
            'http://redireccion.test/l/'.$pagina->slug,
        ] as $prohibida) {
            $this->assertNotNull($validador->error($prohibida), "Debe seguir prohibida: {$prohibida}");
        }
    }

    public function test_nfc_lleva_a_la_pagina_mientras_el_qr_de_la_misma_tarjeta_sigue_en_instagram(): void
    {
        $negocio = $this->negocioConPaginas();
        $pagina = $this->publicar($negocio, $this->documento([$this->enlace()]));
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.productos.store'), [
            'negocio_id' => $negocio->id,
            'nombre' => 'Tarjeta doble',
            'tipo' => 'combinado',
            'modalidad_destino' => 'independiente',
            'url_destino' => 'https://www.tiktok.com/@cafe',
            'url_destino_qr' => 'https://www.instagram.com/cafe',
            'estado_preparacion' => 'pendiente',
            'cantidad' => 1,
        ])->assertSessionHasNoErrors();

        $nfc = Producto::query()->principales()->where('negocio_id', $negocio->id)->firstOrFail();
        $qr = $nfc->enlacesSecundarios()->sole();
        $otroProducto = Producto::factory()->for($negocio)->create(['url_destino' => 'https://ejemplo-otro.mx']);
        $codigos = [$nfc->codigo, $qr->codigo, $otroProducto->codigo];
        $cambiosAntes = CambioDestino::query()->where('producto_id', $qr->id)->count();
        $this->withHeader('User-Agent', self::UA)->get('/r/'.$qr->codigo)->assertRedirect('https://www.instagram.com/cafe');
        $accesosQr = EventoAcceso::query()->where('producto_id', $qr->id)->count();

        $propietario = $this->propietario($negocio);
        $this->actingAs($propietario)->get(route('cliente.productos.show', $nfc))
            ->assertInertia(fn ($p) => $p->where('destinos_pagina.0.url', $pagina->urlPublica()));

        $this->actingAs($propietario)
            ->put(route('cliente.productos.destino', $nfc), ['url_destino' => $pagina->urlPublica()])
            ->assertSessionHasNoErrors();

        $this->assertSame($pagina->urlPublica(), $nfc->fresh()?->url_destino);
        $this->withHeader('User-Agent', self::UA)->get('/r/'.$nfc->codigo)->assertRedirect($pagina->urlPublica());
        $this->withHeader('User-Agent', self::UA)->get('/r/'.$qr->codigo)->assertRedirect('https://www.instagram.com/cafe');
        $this->get('/r/'.$otroProducto->codigo)->assertRedirect('https://ejemplo-otro.mx');

        // Nada más cambió: códigos, propiedad, historial y estadísticas del QR.
        $this->assertSame($codigos, [$nfc->fresh()?->codigo, $qr->fresh()?->codigo, $otroProducto->fresh()?->codigo]);
        $this->assertSame($negocio->id, $qr->fresh()?->negocio_id);
        $this->assertSame($cambiosAntes, CambioDestino::query()->where('producto_id', $qr->id)->count());
        $this->assertSame($accesosQr + 1, EventoAcceso::query()->where('producto_id', $qr->id)->count());
        $this->assertDatabaseHas('cambios_destino', ['producto_id' => $nfc->id, 'url_nueva' => $pagina->urlPublica()]);

        // El visitante llega a la página publicada.
        $this->get($pagina->urlPublica())->assertOk();
    }
}
