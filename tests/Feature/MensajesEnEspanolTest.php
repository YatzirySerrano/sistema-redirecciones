<?php

namespace Tests\Feature;

use App\Services\Medios\LimitesArchivos;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Ningún mensaje técnico en inglés llega a la persona usuaria, ni en
 * respuestas JSON (fetch) ni en páginas Inertia.
 */
class MensajesEnEspanolTest extends TestCase
{
    use RefreshDatabase;

    private const PROHIBIDOS = ['Unauthenticated', 'Forbidden', 'Server Error', 'This action is unauthorized', 'Not Found',
        'Too Many', 'CSRF token', 'Page Expired', 'Validation failed', 'The given data'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);

        Route::middleware('web')->group(function () {
            Route::get('__prueba/401', fn () => throw new AuthenticationException);
            Route::get('__prueba/403', fn () => throw new AuthorizationException);
            Route::get('__prueba/404', fn () => abort(404));
            Route::post('__prueba/413', fn () => throw new PostTooLargeException);
            Route::get('__prueba/419', fn () => throw new TokenMismatchException);
            Route::get('__prueba/429', fn () => abort(429));
            Route::get('__prueba/500', fn () => throw new RuntimeException('detalle interno'));
            Route::post('__prueba/422', fn () => request()->validate(['email' => ['required', 'email'], 'telefono' => ['required']]));
        });
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function estados(): array
    {
        return [
            '401' => [401, 'Tu sesión expiró. Inicia sesión de nuevo.'],
            '403' => [403, 'No tienes permiso para realizar esta acción.'],
            '404' => [404, 'El recurso solicitado no existe o ya no está disponible.'],
            '419' => [419, 'La sesión expiró. Recarga la página e inténtalo nuevamente.'],
            '429' => [429, 'Demasiadas solicitudes seguidas. Espera un momento e inténtalo nuevamente.'],
            '500' => [500, 'Ocurrió un problema al procesar la solicitud. Inténtalo nuevamente.'],
        ];
    }

    #[DataProvider('estados')]
    public function test_las_respuestas_json_estan_en_espanol(int $estado, string $mensaje): void
    {
        $respuesta = $this->getJson("__prueba/{$estado}");

        $respuesta->assertStatus($estado)->assertExactJson(['message' => $mensaje]);
        $this->sinTextoTecnico((string) $respuesta->getContent());
    }

    public function test_los_errores_de_validacion_json_estan_en_espanol(): void
    {
        $respuesta = $this->postJson('__prueba/422', ['email' => 'no-es-correo'])->assertStatus(422);

        $this->assertSame('Correo electrónico debe ser un correo electrónico válido. (y 1 error más)', $respuesta->json('message'));
        $this->sinTextoTecnico((string) $respuesta->getContent());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function estadosInertia(): array
    {
        return ['403' => [403], '404' => [404], '429' => [429], '500' => [500]];
    }

    #[DataProvider('estadosInertia')]
    public function test_las_paginas_de_error_no_muestran_mensajes_tecnicos(int $estado): void
    {
        $respuesta = $this->get("__prueba/{$estado}");

        $respuesta->assertStatus($estado)->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('Error')
            ->where('estado', $estado)
            ->where('mensaje', null));
        $this->assertStringNotContainsString('detalle interno', (string) $respuesta->getContent());
    }

    public function test_sin_sesion_se_redirige_al_login_y_la_sesion_expirada_avisa_en_espanol(): void
    {
        $this->get('__prueba/401')->assertRedirect(route('login'));

        $this->from('/login')->get('__prueba/419')->assertRedirect('/login');
        $this->assertSame('La sesión expiró. Vuelve a intentarlo.', $this->flashToast());
    }

    public function test_un_archivo_que_supera_el_limite_del_servidor_se_explica_en_espanol(): void
    {
        // El mensaje indica el máximo real que acepta PHP en este servidor.
        $mensaje = app(LimitesArchivos::class)->mensajeExcedido();
        $this->assertStringStartsWith('El archivo es demasiado grande. El máximo que acepta el servidor es ', $mensaje);

        $this->postJson('__prueba/413')->assertStatus(413)
            ->assertJson(['message' => $mensaje]);

        $this->post('__prueba/413')->assertStatus(413)->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('Error')
            ->where('estado', 413)
            ->where('mensaje', $mensaje));
    }

    private function flashToast(): ?string
    {
        $flash = session(SessionKey::FLASH_DATA);

        return is_array($flash) ? ($flash['toast']['message'] ?? null) : null;
    }

    private function sinTextoTecnico(string $contenido): void
    {
        foreach (self::PROHIBIDOS as $texto) {
            $this->assertStringNotContainsString($texto, $contenido);
        }
    }
}
