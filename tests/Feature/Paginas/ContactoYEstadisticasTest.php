<?php

namespace Tests\Feature\Paginas;

use App\Models\Paginas\EventoPagina;
use App\Models\Paginas\MensajePagina;
use App\Models\Paginas\PaginaEnlaces;
use App\Notifications\Paginas\NuevoMensajePagina;
use App\Services\Paginas\ProteccionContacto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\PreparaPaginas;
use Tests\TestCase;

class ContactoYEstadisticasTest extends TestCase
{
    use PreparaPaginas, RefreshDatabase;

    private const MOVIL = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';

    private const ESCRITORIO = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    /**
     * @return array{0: PaginaEnlaces, 1: string}
     */
    private function paginaConContacto(bool $notificar = false): array
    {
        $negocio = $this->negocioConPaginas();
        $bloque = (string) Str::uuid();
        $pagina = $this->publicar($negocio, $this->documento([
            ['id' => $bloque, 'tipo' => 'contacto', 'titulo' => 'Escríbenos'],
        ], ['ajustes' => ['notificar_mensajes' => $notificar]]));

        return [$pagina, $bloque];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function envio(PaginaEnlaces $pagina, string $bloque, array $datos = [], ?int $hace = 10): array
    {
        return [
            'bloque' => $bloque,
            'nombre' => 'Ana López',
            'email' => 'ana@correo.mx',
            'mensaje' => 'Hola, quisiera información sobre sus horarios.',
            'sitio_web' => '',
            'marca' => app(ProteccionContacto::class)->marca($pagina, time() - (int) $hace),
            ...$datos,
        ];
    }

    public function test_formulario_de_contacto_guarda_el_mensaje_y_avisa_al_negocio(): void
    {
        Notification::fake();
        [$pagina, $bloque] = $this->paginaConContacto(notificar: true);
        $propietario = $this->propietario($pagina->negocio);

        $this->postJson("/l/{$pagina->slug}/contacto", $this->envio($pagina, $bloque, ['nombre' => '<b>Ana</b>']))
            ->assertCreated()->assertJsonPath('ok', true);

        $mensaje = MensajePagina::query()->sole();
        $this->assertSame($pagina->negocio_id, $mensaje->negocio_id);
        $this->assertSame('Ana', $mensaje->nombre);
        $this->assertDatabaseHas('paginas_enlaces_diarios', ['pagina_id' => $pagina->id, 'tipo' => 'contacto', 'total' => 1]);
        Notification::assertSentTo($propietario, NuevoMensajePagina::class);
    }

    public function test_protecciones_contra_spam(): void
    {
        [$pagina, $bloque] = $this->paginaConContacto();
        $url = "/l/{$pagina->slug}/contacto";

        // Campo trampa: parece aceptado pero no se guarda.
        $this->postJson($url, $this->envio($pagina, $bloque, ['sitio_web' => 'https://spam.mx']))->assertOk();
        // Envío instantáneo (menos del tiempo mínimo).
        $this->postJson($url, $this->envio($pagina, $bloque, [], hace: 0))->assertStatus(422)->assertJsonValidationErrors('marca');
        // Marca alterada o caducada.
        $this->postJson($url, $this->envio($pagina, $bloque, ['marca' => time().'.'.str_repeat('a', 64)]))->assertStatus(422);
        $this->postJson($url, $this->envio($pagina, $bloque, [], hace: 3 * 86400))->assertStatus(422);
        // Longitudes y enlaces.
        $this->postJson($url, $this->envio($pagina, $bloque, ['mensaje' => str_repeat('a', 2001)]))->assertStatus(422);
        $this->postJson($url, $this->envio($pagina, $bloque, ['mensaje' => 'Visita https://a.mx https://b.mx www.c.mx ya']))->assertStatus(422)->assertJsonValidationErrors('mensaje');
        $this->postJson($url, $this->envio($pagina, $bloque, ['email' => 'no-es-correo']))->assertStatus(422);
        // Elemento que no es un formulario publicado.
        $this->postJson($url, $this->envio($pagina, (string) Str::uuid()))->assertNotFound();

        $this->assertSame(0, MensajePagina::query()->count());

        // Límite por IP: 5 envíos válidos cada 10 minutos.
        for ($i = 0; $i < 4; $i++) {
            $this->postJson($url, $this->envio($pagina, $bloque))->assertCreated();
        }
        $this->postJson($url, $this->envio($pagina, $bloque))->assertStatus(429);
        $this->assertSame(4, MensajePagina::query()->count());
    }

    public function test_estadisticas_registran_visitas_clics_sin_bots_ni_precargas(): void
    {
        $negocio = $this->negocioConPaginas();
        $enlace = $this->enlace('Menú', 'https://cafe-aurora.mx/menu');
        $otro = $this->enlace('Reservas', 'https://cafe-aurora.mx/reservas');
        $pagina = $this->publicar($negocio, $this->documento([$enlace, $otro]));
        $ruta = '/l/'.$pagina->slug;

        $this->withHeaders(['User-Agent' => self::MOVIL, 'Referer' => 'https://www.instagram.com/p/abc?utm=1'])->get($ruta)->assertOk();
        $this->withHeaders(['User-Agent' => self::ESCRITORIO, 'Referer' => ''])->get($ruta)->assertOk();
        $this->withHeaders(['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)'])->get($ruta)->assertOk();
        $this->withHeaders(['User-Agent' => self::MOVIL, 'Sec-Purpose' => 'prefetch'])->get($ruta)->assertOk();
        $this->flushHeaders();
        $this->withHeaders(['User-Agent' => self::MOVIL])->get("{$ruta}/ir/{$enlace['id']}");
        $this->withHeaders(['User-Agent' => self::ESCRITORIO])->get("{$ruta}/ir/{$enlace['id']}");
        $this->withHeaders(['User-Agent' => self::MOVIL])->get("{$ruta}/ir/{$otro['id']}");

        $this->assertSame(3, EventoPagina::query()->where('tipo', 'visita')->count(), 'La precarga no se registra.');
        $this->assertSame(1, EventoPagina::query()->where('es_bot', true)->count());
        $this->assertSame(['instagram.com'], EventoPagina::query()->whereNotNull('referente')->pluck('referente')->all(), 'Solo el host, nunca la URL completa.');
        $this->assertDatabaseMissing('paginas_enlaces_eventos', ['referente' => 'https://www.instagram.com/p/abc?utm=1']);
        $this->assertSame(0, EventoPagina::query()->where('tipo', 'clic')->whereNotNull('huella_diaria')->count());

        $propietario = $this->propietario($negocio);
        $this->actingAs($propietario)->get(route('cliente.paginas.estadisticas.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('cliente/paginas/Estadisticas')
                ->where('totales.visitas', 2)
                ->where('totales.clics', 3)
                ->where('totales.bots', 1)
                ->where('totales.ctr', 150)
                ->where('totales.visitantes_estimados', 2)
                ->where('elementos.0.id', $enlace['id'])
                ->where('elementos.0.clics', 2)
                ->where('elementos.0.nombre', 'Menú')
                ->where('referentes', fn ($r) => collect($r)->pluck('clave')->contains('instagram.com'))
                ->where('dispositivos', fn ($d) => collect($d)->sum('total') === 2));

        // Aislamiento: otro negocio no ve nada de esta página.
        $ajeno = $this->negocioConPaginas();
        $this->actingAs($this->propietario($ajeno))->get(route('cliente.paginas.estadisticas.index'))
            ->assertInertia(fn ($p) => $p->where('totales.visitas', 0)->where('totales.clics', 0)->where('elementos', []));
    }

    public function test_exportacion_csv(): void
    {
        $negocio = $this->negocioConPaginas();
        $enlace = $this->enlace('=HYPERLINK("x")', 'https://cafe-aurora.mx');
        $pagina = $this->publicar($negocio, $this->documento([$enlace]));
        $this->withHeader('User-Agent', self::MOVIL)->get("/l/{$pagina->slug}");
        $this->withHeader('User-Agent', self::MOVIL)->get("/l/{$pagina->slug}/ir/{$enlace['id']}");

        $respuesta = $this->actingAs($this->propietario($negocio))->get(route('cliente.paginas.estadisticas.exportar'))->assertOk();
        $csv = $respuesta->streamedContent();

        $this->assertStringContainsString('text/csv', (string) $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('Visitas,Clics', $csv);
        $this->assertStringContainsString(now()->toDateString().',1,1', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv, 'Se neutralizan fórmulas.');
    }

    public function test_depuracion_conserva_totales_y_elimina_huellas_y_eventos_antiguos(): void
    {
        $negocio = $this->negocioConPaginas();
        $pagina = $this->publicar($negocio, $this->documento([$this->enlace()]));
        $this->withHeader('User-Agent', self::MOVIL)->get("/l/{$pagina->slug}");
        EventoPagina::query()->update(['ocurrido_en' => now()->subDays(500)]);

        $this->artisan('dasti:depurar-paginas')->assertSuccessful();

        $this->assertSame(0, EventoPagina::query()->count());
        $this->assertDatabaseHas('paginas_enlaces_diarios', ['pagina_id' => $pagina->id, 'tipo' => 'visita', 'total' => 1]);
    }
}
