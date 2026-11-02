<?php

namespace Tests\Feature\Cuenta;

use App\Actions\Usuarios\EnviarInvitacion;
use App\Enums\EstadoCuenta;
use App\Enums\ResultadoInvitacion;
use App\Models\Negocio;
use App\Models\RegistroAuditoria;
use App\Models\User;
use App\Notifications\InvitacionAcceso;
use App\Notifications\RestablecerContrasena;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/**
 * Correos de la plataforma: invitaciones y recuperación de contraseña.
 * Nunca se envían correos reales: se usan Notification::fake() y el mailer
 * "array" de phpunit.xml.
 */
class CorreosTest extends TestCase
{
    use RefreshDatabase;

    private Negocio $negocio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->negocio = Negocio::factory()->create(['nombre_comercial' => 'Punto Polar Hielo y Agua']);
    }

    public function test_la_invitacion_se_envia_en_cola_una_sola_vez_con_reintentos(): void
    {
        Notification::fake();
        $usuario = User::factory()->cliente($this->negocio)->create(['name' => 'Ana Ruiz']);

        $resultado = app(EnviarInvitacion::class)->ejecutar($usuario);

        $this->assertSame(ResultadoInvitacion::Enviada, $resultado);
        Notification::assertSentToTimes($usuario, InvitacionAcceso::class, 1);
        Notification::assertSentTo($usuario, InvitacionAcceso::class, function (InvitacionAcceso $n) use ($usuario) {
            $this->assertInstanceOf(ShouldQueue::class, $n);
            $this->assertTrue($n->afterCommit);
            $this->assertSame(3, $n->tries);
            $this->assertSame([60, 300], $n->backoff());
            $this->assertTrue($n->shouldSend($usuario, 'mail'));

            return true;
        });
        $this->assertSame('pendiente', $usuario->fresh()->estadoInvitacion());
    }

    public function test_la_plantilla_de_invitacion_esta_en_espanol_y_sin_marcas_ajenas(): void
    {
        $usuario = User::factory()->cliente($this->negocio)->create(['name' => 'Ana Ruiz']);
        $html = (string) (new InvitacionAcceso('https://redireccion.test/invitacion/1?firma=x', 72))->toMail($usuario)->render();

        $this->assertStringContainsString('Hola, Ana Ruiz', $html);
        $this->assertStringContainsString('Punto Polar Hielo y Agua', $html);
        $this->assertStringContainsString('Definir mi contraseña', $html);
        $this->assertStringContainsString('caduca en 3 días', $html);
        $this->assertStringContainsString('puedes ignorarlo', $html);
        $this->assertStringContainsString('DASTI', $html);
        $this->assertStringContainsString('Saludos', $html);
        $this->assertStringContainsString('copia y pega la siguiente dirección', $html);

        foreach (['Regards', 'Hello!', 'having trouble', 'All rights reserved', 'laravel.com', 'Laravel Logo'] as $ingles) {
            $this->assertStringNotContainsString($ingles, $html);
        }
    }

    public function test_la_recuperacion_envia_un_correo_en_espanol_con_caducidad(): void
    {
        Notification::fake();
        $usuario = User::factory()->cliente($this->negocio)->create();

        $this->post(route('password.email'), ['email' => $usuario->email])->assertSessionHasNoErrors();

        Notification::assertSentToTimes($usuario, RestablecerContrasena::class, 1);
        Notification::assertSentTo($usuario, RestablecerContrasena::class, function (RestablecerContrasena $n) use ($usuario) {
            $html = (string) $n->toMail($usuario)->render();
            $this->assertStringContainsString('Restablecer contraseña', $html);
            $this->assertStringContainsString('caduca en 60 minutos', $html);
            $this->assertStringContainsString('Si no solicitaste el cambio', $html);
            $this->assertStringNotContainsString('Reset Password', $html);
            $this->assertInstanceOf(ShouldQueue::class, $n);

            return true;
        });
    }

    public function test_un_usuario_suspendido_no_recibe_correos_y_la_respuesta_no_lo_revela(): void
    {
        Notification::fake();
        $suspendido = User::factory()->cliente($this->negocio)->suspendido()->create();

        $conCuenta = $this->post(route('password.email'), ['email' => $suspendido->email]);
        $sinCuenta = $this->post(route('password.email'), ['email' => 'nadie@desconocido.test']);

        $this->assertSame(session('status'), trans(Password::RESET_LINK_SENT));
        $conCuenta->assertSessionHasNoErrors();
        $sinCuenta->assertSessionHasNoErrors();
        $this->assertSame(ResultadoInvitacion::NoPermitida, app(EnviarInvitacion::class)->ejecutar($suspendido));
        Notification::assertNothingSent();
    }

    public function test_un_enlace_de_recuperacion_caducado_o_ya_usado_no_funciona(): void
    {
        $usuario = User::factory()->cliente($this->negocio)->create();
        $token = Password::broker()->createToken($usuario);
        $datos = ['token' => $token, 'email' => $usuario->email, 'password' => 'Nueva-Clave-2026', 'password_confirmation' => 'Nueva-Clave-2026'];

        $this->post(route('password.update'), $datos)->assertSessionHasNoErrors();
        // Segundo uso del mismo enlace.
        $this->post(route('password.update'), $datos)->assertSessionHasErrors('email');

        $otro = Password::broker()->createToken($usuario->fresh());
        $this->travel(61)->minutes();
        $this->post(route('password.update'), [...$datos, 'token' => $otro])->assertSessionHasErrors('email');
    }

    public function test_un_error_smtp_marca_la_invitacion_como_fallida_una_sola_vez(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1,
            'mail.mailers.smtp.timeout' => 2,
        ]);
        $usuario = User::factory()->cliente($this->negocio)->create();

        $resultado = app(EnviarInvitacion::class)->ejecutar($usuario);

        $this->assertSame(ResultadoInvitacion::Fallida, $resultado);
        $this->assertSame('fallida', $usuario->fresh()->estadoInvitacion());
        $this->assertSame(1, RegistroAuditoria::query()->where('accion', 'usuario.invitacion_fallida')->count());
        $this->assertSame(0, RegistroAuditoria::query()->where('accion', 'usuario.invitacion_enviada')->count());
    }

    public function test_agotar_los_reintentos_en_la_cola_registra_el_fallo(): void
    {
        Notification::fake();
        $usuario = User::factory()->cliente($this->negocio)->create();
        app(EnviarInvitacion::class)->ejecutar($usuario);

        $notificacion = Notification::sent($usuario, InvitacionAcceso::class)->first();
        $notificacion->failed(new TransportException('Connection refused'));
        $notificacion->failed(new TransportException('Connection refused'));

        $this->assertSame('fallida', $usuario->fresh()->estadoInvitacion());
        $this->assertSame(1, RegistroAuditoria::query()->where('accion', 'usuario.invitacion_fallida')->count());
    }

    public function test_un_enlace_sustituido_o_una_cuenta_suspendida_no_reciben_el_correo_pendiente(): void
    {
        Notification::fake();
        $usuario = User::factory()->cliente($this->negocio)->create();

        app(EnviarInvitacion::class)->ejecutar($usuario);
        $primera = Notification::sent($usuario, InvitacionAcceso::class)->first();

        $this->travel(5)->seconds();
        app(EnviarInvitacion::class)->ejecutar($usuario->fresh());
        $segunda = Notification::sent($usuario, InvitacionAcceso::class)->last();

        // Si ambas siguen en la cola, solo sale la más reciente.
        $this->assertFalse($primera->shouldSend($usuario, 'mail'));
        $this->assertTrue($segunda->shouldSend($usuario, 'mail'));

        $usuario->forceFill(['estado' => EstadoCuenta::Suspendido])->save();
        $this->assertFalse($segunda->shouldSend($usuario, 'mail'));
        $this->assertFalse((new RestablecerContrasena('token'))->shouldSend($usuario, 'mail'));
    }
}
