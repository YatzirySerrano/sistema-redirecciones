<?php

namespace Tests\Feature\Redirecciones;

use App\Enums\EstadoProducto;
use App\Models\EventoAcceso;
use App\Models\Producto;
use App\Services\Redirecciones\DetectorVistaPrevia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Vista previa al compartir un enlace corto: los robots de mensajería y
 * redes sociales reciben una tarjeta genérica de DASTI (sin el destino) y
 * no cuentan en las estadísticas; las personas siguen siendo redirigidas.
 */
class VistaPreviaTest extends TestCase
{
    use RefreshDatabase;

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    /**
     * @return array<string, array{string}>
     */
    public static function robotsDeVistaPrevia(): array
    {
        return [
            'WhatsApp' => ['WhatsApp/2.23.20.0 A'],
            'Facebook' => ['facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)'],
            'Facebot' => ['Facebot'],
            'X' => ['Twitterbot/1.0'],
            'Telegram' => ['TelegramBot (like TwitterBot)'],
            'LinkedIn' => ['LinkedInBot/1.0 (compatible; Mozilla/5.0; Apache-HttpClient +http://www.linkedin.com)'],
            'Slack' => ['Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)'],
            'Discord' => ['Mozilla/5.0 (compatible; Discordbot/2.0; +https://discordapp.com)'],
            'Teams/Skype' => ['Mozilla/5.0 (Windows NT 6.1; WOW64) SkypeUriPreview Preview/0.5'],
            'iMessage' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_1) AppleWebKit/601.2.4 (KHTML, like Gecko) Version/9.0.1 Safari/601.2.4 facebookexternalhit/1.1 Facebot Twitterbot/1.0'],
        ];
    }

    #[DataProvider('robotsDeVistaPrevia')]
    public function test_los_robots_de_vista_previa_reciben_la_tarjeta_de_dasti(string $agente): void
    {
        $producto = Producto::factory()->create(['url_destino' => 'https://www.example.org/menu-secreto']);

        $respuesta = $this->withHeader('User-Agent', $agente)->get('/r/'.$producto->codigo);

        $respuesta->assertOk()
            ->assertSee('<meta property="og:title" content="Enlace verificado · DASTI">', false)
            ->assertSee('og/dasti-og.png', false)
            ->assertSee('<meta property="og:image:width" content="1200">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertSee('/r/'.$producto->codigo, false)
            ->assertDontSee('example.org')
            ->assertDontSee('menu-secreto');

        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));
        $this->assertStringContainsString('User-Agent', (string) $respuesta->headers->get('Vary'));
        $this->assertSame('noindex, nofollow', $respuesta->headers->get('X-Robots-Tag'));
        $this->assertEmpty($respuesta->headers->getCookies());
    }

    public function test_la_vista_previa_no_se_registra_en_estadisticas(): void
    {
        config(['dasti.eventos_en_cola' => true]);
        $producto = Producto::factory()->create();

        $this->withHeader('User-Agent', 'WhatsApp/2.23.20.0 A')->get('/r/'.$producto->codigo)->assertOk();
        $this->withHeader('User-Agent', 'facebookexternalhit/1.1')->get('/r/'.$producto->codigo)->assertOk();

        $this->assertSame(0, EventoAcceso::query()->count());
        $this->assertSame(0, $producto->fresh()->total_accesos);
        $this->assertSame(0, DB::table('accesos_diarios')->where('producto_id', $producto->id)->count());
    }

    public function test_las_personas_siguen_siendo_redirigidas_y_contadas(): void
    {
        config(['dasti.eventos_en_cola' => true]);
        $producto = Producto::factory()->create(['url_destino' => 'https://www.example.org/menu']);

        $this->withHeader('User-Agent', self::IPHONE)->get('/r/'.$producto->codigo)
            ->assertStatus(302)
            ->assertRedirect('https://www.example.org/menu');

        $this->assertSame(1, $producto->fresh()->total_accesos);
    }

    public function test_los_buscadores_y_navegadores_de_apps_no_reciben_la_tarjeta(): void
    {
        $producto = Producto::factory()->create(['url_destino' => 'https://www.example.org/menu']);

        foreach ([
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.1.1 Safari/605.1.15 (Applebot/0.1; +http://www.apple.com/go/applebot)',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Instagram 300.0.0.0',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 [FBAN/FBIOS;FBAV/440.0.0]',
        ] as $agente) {
            $this->withHeader('User-Agent', $agente)->get('/r/'.$producto->codigo)
                ->assertRedirect('https://www.example.org/menu');
        }
    }

    public function test_un_enlace_inactivo_no_muestra_la_tarjeta_ni_se_registra(): void
    {
        config(['dasti.eventos_en_cola' => true]);
        $producto = Producto::factory()->create(['estado' => EstadoProducto::Suspendido]);

        $this->withHeader('User-Agent', 'WhatsApp/2.23.20.0 A')->get('/r/'.$producto->codigo)
            ->assertStatus(410)
            ->assertDontSee('Enlace verificado');

        $this->withHeader('User-Agent', 'WhatsApp/2.23.20.0 A')->get('/r/zzzzzzzzzz')->assertNotFound();

        $this->assertSame(0, EventoAcceso::query()->count());
    }

    public function test_el_detector_ignora_agentes_vacios(): void
    {
        $detector = new DetectorVistaPrevia;

        $this->assertFalse($detector->esVistaPrevia(null));
        $this->assertFalse($detector->esVistaPrevia(''));
        $this->assertFalse($detector->esVistaPrevia(self::IPHONE));
        $this->assertTrue($detector->esVistaPrevia('WhatsApp/2.24.1.6 i'));
    }
}
