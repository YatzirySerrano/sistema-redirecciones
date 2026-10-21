<?php

namespace Tests\Feature\Cuenta;

use App\Models\Producto;
use App\Models\RegistroAuditoria;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Tests\TestCase;

/**
 * Seguridad de las cuentas: verificación en dos pasos obligatoria para
 * administradores, cierre de sesiones al cambiar credenciales y auditoría de
 * los eventos de seguridad.
 */
class SeguridadCuentasTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sesión guardada en la tabla `sessions`, como en producción
     * (SESSION_DRIVER=database; las pruebas usan el driver «array»).
     */
    private function sesionDe(User $usuario, string $id): void
    {
        config(['session.driver' => 'database']);

        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $usuario->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'prueba', 'payload' => '', 'last_activity' => time(),
        ]);
    }

    public function test_con_la_politica_activa_el_administrador_sin_2fa_es_enviado_a_configurarla(): void
    {
        config(['dasti.exigir_2fa_administradores' => true]);
        $admin = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.panel'))->assertRedirect(route('security.edit'));
        $this->actingAs($admin)->get(route('admin.negocios.index'))->assertRedirect(route('security.edit'));
        $this->actingAs($admin)->putJson(route('admin.productos.destino', Producto::factory()->create()), ['url_destino' => 'https://www.example.org/x'])
            ->assertForbidden();

        // Ajustes › Seguridad sigue disponible para activarla.
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))->assertOk();
    }

    public function test_con_2fa_confirmada_el_administrador_usa_el_panel(): void
    {
        config(['dasti.exigir_2fa_administradores' => true]);
        $admin = User::factory()->withTwoFactor()->create();

        $this->actingAs($admin)->get(route('admin.panel'))->assertOk();
    }

    public function test_la_politica_no_afecta_a_los_clientes_ni_aplica_si_esta_desactivada(): void
    {
        config(['dasti.exigir_2fa_administradores' => true]);
        $cliente = User::factory()->cliente()->create();
        $this->actingAs($cliente)->get(route('cliente.inicio'))->assertOk();

        config(['dasti.exigir_2fa_administradores' => false]);
        $this->actingAs(User::factory()->create())->get(route('admin.panel'))->assertOk();
    }

    public function test_cambiar_la_contrasena_cierra_las_demas_sesiones_y_queda_auditado(): void
    {
        $usuario = User::factory()->cliente()->create();
        $this->sesionDe($usuario, 'sesion-en-otro-dispositivo');
        $tokenAnterior = $usuario->remember_token;

        $this->actingAs($usuario)->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'NuevaClave-2026',
            'password_confirmation' => 'NuevaClave-2026',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-en-otro-dispositivo']);
        $this->assertNotSame($tokenAnterior, $usuario->fresh()->remember_token);
        $this->assertAuthenticatedAs($usuario);

        $registro = RegistroAuditoria::query()->where('accion', 'usuario.contrasena_cambiada')->sole();
        $this->assertSame($usuario->id, $registro->user_id);
        $this->assertStringNotContainsString('NuevaClave', (string) json_encode($registro->toArray()));
    }

    public function test_la_contrasena_temporal_del_administrador_cierra_todas_las_sesiones(): void
    {
        $admin = User::factory()->create();
        $cliente = User::factory()->cliente()->create();
        $this->sesionDe($cliente, 'sesion-del-cliente');

        $this->actingAs($admin)->put(route('admin.usuarios.contrasena', $cliente), [
            'password' => 'Temporal-2026',
            'password_confirmation' => 'Temporal-2026',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['user_id' => $cliente->id]);
        $this->assertTrue($cliente->fresh()->debe_cambiar_password);
    }

    public function test_la_contrasena_inicial_solo_se_cambia_mientras_es_temporal(): void
    {
        $usuario = User::factory()->cliente()->create();

        $this->actingAs($usuario)->put(route('contrasena-inicial.update'), [
            'current_password' => 'password',
            'password' => 'OtraClave-2026',
            'password_confirmation' => 'OtraClave-2026',
        ])->assertForbidden();
    }

    public function test_el_perfil_normaliza_el_correo_y_registra_el_cambio(): void
    {
        $admin = User::factory()->create(['email' => 'admin@dasti.test']);

        $this->actingAs($admin)->patch(route('profile.update'), [
            'name' => 'Admin Renombrado',
            'email' => '  Admin.Nuevo@DASTI.test ',
        ])->assertSessionHasNoErrors();

        $this->assertSame('admin.nuevo@dasti.test', $admin->fresh()->email);
        $registro = RegistroAuditoria::query()->where('accion', 'usuario.perfil_actualizado')->sole();
        $this->assertSame('admin@dasti.test', $registro->valores_anteriores['email']);
        $this->assertSame('admin.nuevo@dasti.test', $registro->valores_nuevos['email']);
    }

    public function test_el_inicio_de_sesion_y_los_eventos_de_2fa_quedan_auditados(): void
    {
        $usuario = User::factory()->cliente()->create();

        $this->post(route('login.store'), ['email' => $usuario->email, 'password' => 'password'])->assertRedirect();
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'sesion.iniciada', 'user_id' => $usuario->id]);
        $this->assertNotNull($usuario->fresh()->ultimo_acceso_en);

        event(new TwoFactorAuthenticationConfirmed($usuario));
        event(new TwoFactorAuthenticationDisabled($usuario));
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'usuario.2fa_activada', 'user_id' => $usuario->id]);
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'usuario.2fa_desactivada', 'user_id' => $usuario->id]);

        event(new Lockout(Request::create('/login', 'POST', ['email' => 'Alguien@Example.com'])));
        $bloqueo = RegistroAuditoria::query()->where('accion', 'sesion.bloqueada')->sole();
        $this->assertSame(['correo' => 'alguien@example.com'], $bloqueo->valores_nuevos);
    }

    public function test_el_limite_de_reenvio_del_restablecimiento_no_revela_si_el_correo_existe(): void
    {
        Notification::fake();
        $usuario = User::factory()->cliente()->create();

        $this->post(route('password.email'), ['email' => $usuario->email])->assertSessionHasNoErrors();
        // Segunda solicitud inmediata: el broker la limita, pero la respuesta
        // es idéntica a la de un correo inexistente.
        $existente = $this->post(route('password.email'), ['email' => $usuario->email]);
        $inexistente = $this->post(route('password.email'), ['email' => 'nadie@example.com']);

        $existente->assertSessionHasNoErrors();
        $inexistente->assertSessionHasNoErrors();
        $this->assertSame($inexistente->getSession()->get('status'), $existente->getSession()->get('status'));
    }

    public function test_la_suspension_cierra_sesiones_y_bloquea_la_operacion(): void
    {
        $admin = User::factory()->create();
        $cliente = User::factory()->cliente()->create();
        $this->sesionDe($cliente, 'sesion-activa');

        $this->actingAs($admin)->patch(route('admin.usuarios.estado', $cliente), ['estado' => 'suspendido', 'motivo' => 'Prueba'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['user_id' => $cliente->id]);
        $this->actingAs($cliente->fresh())->get(route('cliente.inicio'))->assertRedirect(route('login'));
    }
}
