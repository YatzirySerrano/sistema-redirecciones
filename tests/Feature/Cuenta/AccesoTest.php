<?php

namespace Tests\Feature\Cuenta;

use App\Models\Negocio;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccesoTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_usuario_suspendido_no_puede_iniciar_sesion(): void
    {
        $usuario = User::factory()->cliente()->suspendido()->create();

        $this->post(route('login.store'), ['email' => $usuario->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_el_cliente_de_un_negocio_suspendido_no_puede_iniciar_sesion(): void
    {
        $usuario = User::factory()->cliente(Negocio::factory()->suspendido()->create())->create();

        $this->post(route('login.store'), ['email' => $usuario->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_la_sesion_activa_se_cierra_si_el_negocio_se_suspende(): void
    {
        $negocio = Negocio::factory()->create();
        $cliente = User::factory()->cliente($negocio)->create();

        $this->actingAs($cliente)->get(route('cliente.inicio'))->assertOk();

        $negocio->forceFill(['estado' => 'suspendido'])->save();

        $this->actingAs($cliente->fresh())->get(route('cliente.inicio'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_contrasena_incorrecta_no_revela_si_la_cuenta_esta_suspendida(): void
    {
        $usuario = User::factory()->suspendido()->create();

        $this->post(route('login.store'), ['email' => $usuario->email, 'password' => 'incorrecta'])
            ->assertSessionHasErrors(['email' => 'Estas credenciales no coinciden con nuestros registros.']);
    }

    public function test_el_inicio_redirige_segun_el_rol(): void
    {
        $this->actingAs(User::factory()->create())->get(route('inicio'))->assertRedirect(route('admin.panel'));
        $this->actingAs(User::factory()->cliente()->create())->get(route('inicio'))->assertRedirect(route('cliente.inicio'));
    }

    public function test_el_restablecimiento_de_contrasena_completa_la_invitacion(): void
    {
        Notification::fake();
        $usuario = User::factory()->cliente()->conContrasenaTemporal()->create();

        $this->post(route('password.email'), ['email' => $usuario->email]);

        Notification::assertSentTo($usuario, ResetPassword::class, function (ResetPassword $notificacion) use ($usuario) {
            $this->post(route('password.update'), [
                'token' => $notificacion->token,
                'email' => $usuario->email,
                'password' => 'Nueva-Clave-123',
                'password_confirmation' => 'Nueva-Clave-123',
            ])->assertSessionHasNoErrors();

            return true;
        });

        $this->assertFalse($usuario->fresh()->debe_cambiar_password);
    }

    public function test_solicitar_restablecimiento_no_revela_si_el_correo_existe(): void
    {
        $this->post(route('password.email'), ['email' => 'nadie@ninguno.test'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');
    }

    public function test_la_contrasena_inicial_debe_ser_distinta_de_la_temporal(): void
    {
        $usuario = User::factory()->cliente()->conContrasenaTemporal()->create();

        $this->actingAs($usuario)->put(route('contrasena-inicial.update'), [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');
    }
}
