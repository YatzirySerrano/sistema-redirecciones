<?php

namespace Tests\Feature\Redirecciones;

use App\Models\User;
use App\Services\Configuracion\AjustesPlataforma;
use App\Services\Redirecciones\ValidadorUrlDestino;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * La comprobación en vivo del formulario usa exactamente las reglas del
 * servidor: nunca marca como válida una URL que luego se rechazará.
 */
class ValidacionDestinoEnVivoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function urls(): array
    {
        return [
            'válida' => ['https://www.instagram.com/negocio'],
            'internacional' => ['https://cafetería.mx/menú'],
            'http no permitido' => ['http://example.com'],
            'localhost' => ['https://localhost/admin'],
            'ip' => ['https://8.8.8.8/'],
            'puerto' => ['https://example.com:8080/'],
            'acortador' => ['https://bit.ly/abc'],
            'credenciales' => ['https://usuario:clave@example.com'],
            'propio' => ['https://redireccion.test/r/abcdefghjk'],
            'javascript' => ['javascript:alert(1)'],
        ];
    }

    #[DataProvider('urls')]
    public function test_coincide_con_la_validacion_al_guardar(string $url): void
    {
        $cliente = User::factory()->cliente()->create();
        $esperado = app(ValidadorUrlDestino::class)->error($url);

        $respuesta = $this->actingAs($cliente)->postJson(route('destinos.validacion'), ['url' => $url])->assertOk();

        $this->assertSame($esperado === null, $respuesta->json('valida'));
        $this->assertSame($esperado, $respuesta->json('mensaje'));
    }

    public function test_devuelve_la_url_normalizada_dominio_y_seguridad(): void
    {
        $this->actingAs(User::factory()->cliente()->create())
            ->postJson(route('destinos.validacion'), ['url' => 'https://www.Cafetería.mx/menú'])
            ->assertOk()
            ->assertJson([
                'valida' => true,
                'url' => 'https://www.xn--cafetera-i2a.mx/men%C3%BA',
                'dominio' => 'xn--cafetera-i2a.mx',
                'segura' => true,
            ]);
    }

    public function test_respeta_la_configuracion_de_destinos_http(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin)->postJson(route('destinos.validacion'), ['url' => 'http://example.com'])->assertJson(['valida' => false]);

        app(AjustesPlataforma::class)->guardar(['permitir_destinos_http' => true]);

        $this->actingAs($admin)->postJson(route('destinos.validacion'), ['url' => 'http://example.com'])
            ->assertJson(['valida' => true, 'segura' => false]);
    }

    public function test_requiere_sesion_y_esta_limitada(): void
    {
        $this->postJson(route('destinos.validacion'), ['url' => 'https://example.com'])->assertUnauthorized();

        $cliente = User::factory()->cliente()->create();
        for ($i = 0; $i < 60; $i++) {
            $this->actingAs($cliente)->postJson(route('destinos.validacion'), ['url' => 'https://example.com']);
        }
        $this->actingAs($cliente)->postJson(route('destinos.validacion'), ['url' => 'https://example.com'])->assertTooManyRequests();
    }
}
