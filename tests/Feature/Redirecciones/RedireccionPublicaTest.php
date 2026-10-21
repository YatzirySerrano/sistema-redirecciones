<?php

namespace Tests\Feature\Redirecciones;

use App\Enums\CategoriaDispositivo;
use App\Enums\ResultadoAcceso;
use App\Models\EventoAcceso;
use App\Models\Negocio;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RedireccionPublicaTest extends TestCase
{
    use RefreshDatabase;

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    public function test_redirige_con_302_sin_cache_ni_cookies(): void
    {
        $producto = Producto::factory()->create(['url_destino' => 'https://www.example.org/menu']);

        $respuesta = $this->withHeader('User-Agent', self::IPHONE)->get('/r/'.$producto->codigo);

        $respuesta->assertStatus(302)->assertRedirect('https://www.example.org/menu');
        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));
        $this->assertSame('no-referrer', $respuesta->headers->get('Referrer-Policy'));
        $this->assertEmpty($respuesta->headers->getCookies(), 'La redirección no debe crear cookies ni sesión.');
    }

    public function test_acepta_el_codigo_en_mayusculas(): void
    {
        $producto = Producto::factory()->create();

        $this->get('/r/'.strtoupper($producto->codigo))->assertRedirect($producto->url_destino);
    }

    public function test_registra_el_evento_y_actualiza_agregados(): void
    {
        $producto = Producto::factory()->create();

        $this->withHeader('User-Agent', self::IPHONE)->get('/r/'.$producto->codigo)->assertStatus(302);

        $evento = EventoAcceso::query()->sole();
        $this->assertSame($producto->id, $evento->producto_id);
        $this->assertSame($producto->negocio_id, $evento->negocio_id);
        $this->assertSame(ResultadoAcceso::Redirigido, $evento->resultado);
        $this->assertSame(CategoriaDispositivo::Movil, $evento->dispositivo);
        $this->assertSame('Safari', $evento->navegador);
        $this->assertSame('iOS', $evento->sistema_operativo);
        $this->assertFalse($evento->es_bot);
        $this->assertSame(64, strlen((string) $evento->huella_diaria));

        // No se guarda la IP en ninguna columna.
        $this->assertStringNotContainsString('127.0.0.1', json_encode($evento->getAttributes()));

        $this->assertSame(1, $producto->fresh()->total_accesos);
        $this->assertSame(1, (int) DB::table('accesos_diarios')->where('producto_id', $producto->id)->value('total'));
    }

    public function test_los_bots_no_cuentan_en_los_totales(): void
    {
        // Con varias solicitudes en una misma prueba, los callbacks "after response"
        // se acumulan en la aplicación de pruebas; con la cola síncrona cada
        // evento se procesa exactamente una vez (en producción cada petición es
        // un proceso independiente).
        config(['dasti.eventos_en_cola' => true]);
        $producto = Producto::factory()->create();

        $this->withHeader('User-Agent', 'WhatsApp/2.23.20.0 A')->get('/r/'.$producto->codigo)->assertStatus(302);
        $this->withHeader('User-Agent', 'curl/8.4.0')->get('/r/'.$producto->codigo)->assertStatus(302);

        $this->assertSame(2, EventoAcceso::query()->where('es_bot', true)->count());
        $this->assertSame(0, $producto->fresh()->total_accesos);
        $fila = DB::table('accesos_diarios')->where('producto_id', $producto->id)->first();
        $this->assertSame(0, (int) $fila->total);
        $this->assertSame(2, (int) $fila->bots);
    }

    public function test_las_solicitudes_head_no_se_registran(): void
    {
        $producto = Producto::factory()->create();

        $this->call('HEAD', '/r/'.$producto->codigo)->assertStatus(302);

        $this->assertSame(0, EventoAcceso::query()->count());
    }

    public function test_codigo_inexistente_muestra_pagina_neutra_404(): void
    {
        $this->get('/r/abcdefghjk')
            ->assertNotFound()
            ->assertSee('Este enlace no está disponible')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->get('/r/x')->assertNotFound();
        $this->assertSame(0, EventoAcceso::query()->count());
    }

    public function test_producto_suspendido_no_redirige_ni_expone_datos(): void
    {
        $negocio = Negocio::factory()->create(['nombre_comercial' => 'Negocio Secreto']);
        $producto = Producto::factory()->suspendido()->for($negocio)->create(['url_destino' => 'https://privado.example.org/x']);

        $this->get('/r/'.$producto->codigo)
            ->assertStatus(410)
            ->assertSee('inactivo')
            ->assertDontSee('Negocio Secreto')
            ->assertDontSee('privado.example.org');

        $this->assertSame(ResultadoAcceso::Suspendido, EventoAcceso::query()->sole()->resultado);
    }

    public function test_negocio_suspendido_bloquea_todos_sus_enlaces(): void
    {
        $producto = Producto::factory()->for(Negocio::factory()->suspendido())->create();

        $this->get('/r/'.$producto->codigo)->assertStatus(410);
        $this->assertSame(ResultadoAcceso::NegocioSuspendido, EventoAcceso::query()->sole()->resultado);
    }

    public function test_producto_sin_destino_muestra_no_disponible(): void
    {
        $producto = Producto::factory()->sinDestino()->create();

        $this->get('/r/'.$producto->codigo)->assertNotFound()->assertSee('Este enlace no está disponible');
    }

    public function test_la_ruta_publica_esta_limitada_por_ip(): void
    {
        config(['dasti.limite_redirecciones_por_minuto' => 3]);
        $producto = Producto::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->get('/r/'.$producto->codigo)->assertStatus(302);
        }

        $this->get('/r/'.$producto->codigo)->assertStatus(429);
    }
}
