<?php

namespace Tests\Feature\Cuenta;

use App\Models\Negocio;
use App\Models\RegistroAuditoria;
use App\Models\User;
use App\Notifications\InvitacionAcceso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Invitaciones de acceso: enlace firmado de un solo uso, enviado después de
 * confirmar el alta y sin que un fallo del correo revierta registros.
 */
class InvitacionesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    private function crearNegocioConInvitacion(string $email = 'duena@cafeteria.test', string $estado = 'activo'): void
    {
        $this->actingAs($this->admin)->post(route('admin.negocios.store'), [
            'nombre_comercial' => 'Cafetería Invitada',
            'estado' => $estado,
            'crear_acceso' => true,
            'acceso_nombre' => 'Ana Dueña',
            'acceso_email' => $email,
            'acceso_metodo' => 'invitacion',
        ])->assertSessionHasNoErrors();
    }

    private function enlaceEnviado(User $usuario): string
    {
        $enlace = null;
        Notification::assertSentTo($usuario, InvitacionAcceso::class, function (InvitacionAcceso $n) use (&$enlace) {
            $enlace = $n->url;

            return true;
        });

        return (string) $enlace;
    }

    public function test_el_enlace_se_envia_tras_el_alta_y_no_expone_tokens_ni_contrasenas(): void
    {
        Notification::fake();
        $this->crearNegocioConInvitacion();

        $usuario = User::query()->where('email', 'duena@cafeteria.test')->sole();
        $enlace = $this->enlaceEnviado($usuario);

        $this->assertSame('pendiente', $usuario->estadoInvitacion());
        $this->assertNotNull($usuario->invitacion_enviada_en);
        $this->assertStringContainsString('/invitacion/'.$usuario->id, $enlace);
        $this->assertStringContainsString('signature=', $enlace);
        $this->assertStringContainsString('expires=', $enlace);
        $this->assertDatabaseCount('password_reset_tokens', 0);

        $auditoria = RegistroAuditoria::query()->where('accion', 'usuario.invitacion_enviada')->sole();
        $this->assertStringNotContainsString('signature', (string) json_encode($auditoria->toArray()));
    }

    public function test_aceptar_la_invitacion_define_la_contrasena_inicia_sesion_y_es_de_un_solo_uso(): void
    {
        Notification::fake();
        $this->crearNegocioConInvitacion();
        $usuario = User::query()->where('email', 'duena@cafeteria.test')->sole();
        $enlace = $this->enlaceEnviado($usuario);
        auth()->logout();

        $this->get($enlace)->assertOk()->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('auth/AceptarInvitacion')
            ->where('valida', true)
            ->where('email', 'duena@cafeteria.test'));

        $this->post($enlace, ['password' => 'NuevaClave-2026', 'password_confirmation' => 'NuevaClave-2026'])
            ->assertRedirect(route('inicio'));

        $this->assertAuthenticatedAs($usuario);
        $usuario->refresh();
        $this->assertTrue(Hash::check('NuevaClave-2026', $usuario->password));
        $this->assertFalse($usuario->debe_cambiar_password);
        $this->assertSame('aceptada', $usuario->estadoInvitacion());
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'usuario.invitacion_aceptada', 'user_id' => $usuario->id]);

        // Reutilizar el enlace ya no funciona.
        auth()->logout();
        $this->get($enlace)->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('valida', false)->missing('email'));
        $this->post($enlace, ['password' => 'OtraClave-2026', 'password_confirmation' => 'OtraClave-2026']);
        $this->assertTrue(Hash::check('NuevaClave-2026', $usuario->fresh()->password));
    }

    public function test_el_enlace_caduca_y_no_admite_alteraciones(): void
    {
        Notification::fake();
        $this->crearNegocioConInvitacion();
        $usuario = User::query()->where('email', 'duena@cafeteria.test')->sole();
        $enlace = $this->enlaceEnviado($usuario);
        auth()->logout();

        $otroUsuario = User::factory()->cliente()->create();
        $this->get(str_replace('/invitacion/'.$usuario->id, '/invitacion/'.$otroUsuario->id, $enlace))
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('valida', false));

        $this->travel(73)->hours();
        $this->get($enlace)->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('valida', false));
    }

    public function test_reenviar_la_invitacion_invalida_los_enlaces_anteriores(): void
    {
        Notification::fake();
        $this->crearNegocioConInvitacion();
        $usuario = User::query()->where('email', 'duena@cafeteria.test')->sole();
        $primero = $this->enlaceEnviado($usuario);

        $this->travel(1)->minutes();
        $this->actingAs($this->admin)->post(route('admin.usuarios.invitacion', $usuario))->assertRedirect();

        $enlaces = [];
        Notification::assertSentTo($usuario, InvitacionAcceso::class, function (InvitacionAcceso $n) use (&$enlaces) {
            $enlaces[] = $n->url;

            return true;
        });
        $this->assertCount(2, $enlaces);
        auth()->logout();

        $this->get($primero)->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('valida', false));
        $this->get($enlaces[1])->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('valida', true));
    }

    public function test_un_fallo_del_servidor_de_correo_no_revierte_el_alta_y_queda_registrado(): void
    {
        // Servidor SMTP inexistente en localhost: la conexión se rechaza.
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1,
            'mail.mailers.smtp.timeout' => 2,
        ]);

        $this->crearNegocioConInvitacion();

        $negocio = Negocio::query()->where('nombre_comercial', 'Cafetería Invitada')->sole();
        $usuario = User::query()->where('email', 'duena@cafeteria.test')->sole();

        $this->assertSame($negocio->id, $usuario->negocio_id);
        $this->assertSame('fallida', $usuario->estadoInvitacion());
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'negocio.creado', 'negocio_id' => $negocio->id]);
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'usuario.invitacion_fallida', 'auditable_id' => $usuario->id]);
        $this->assertDatabaseMissing('registros_auditoria', ['accion' => 'usuario.invitacion_enviada']);

        $flash = session(SessionKey::FLASH_DATA)['toast'] ?? null;
        $this->assertSame('warning', $flash['type'] ?? null);
        $this->assertStringContainsString('no pudo enviarse', (string) ($flash['message'] ?? ''));

        // El administrador ve el estado y puede reenviar cuando el correo funcione.
        $this->actingAs($this->admin)->get(route('admin.usuarios.edit', $usuario))
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('usuario.data.invitacion', 'fallida'));

        Notification::fake();
        $this->actingAs($this->admin)->post(route('admin.usuarios.invitacion', $usuario));
        $this->assertSame('pendiente', $usuario->fresh()->estadoInvitacion());
        Notification::assertSentTo($usuario, InvitacionAcceso::class);
    }

    public function test_no_se_envian_invitaciones_a_negocios_o_usuarios_suspendidos(): void
    {
        Notification::fake();
        $this->crearNegocioConInvitacion(estado: 'suspendido');

        $usuario = User::query()->where('email', 'duena@cafeteria.test')->sole();
        Notification::assertNothingSent();
        $this->assertNull($usuario->estadoInvitacion());

        $this->actingAs($this->admin)->post(route('admin.usuarios.invitacion', $usuario))->assertRedirect();
        Notification::assertNothingSent();
    }

    public function test_restablecer_la_contrasena_tambien_completa_una_invitacion_pendiente(): void
    {
        Notification::fake();
        $this->crearNegocioConInvitacion();
        $usuario = User::query()->where('email', 'duena@cafeteria.test')->sole();
        auth()->logout();

        $token = app('auth.password.broker')->createToken($usuario);
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $usuario->email,
            'password' => 'ClaveNueva-2026',
            'password_confirmation' => 'ClaveNueva-2026',
        ])->assertSessionHasNoErrors();

        $this->assertSame('aceptada', $usuario->fresh()->estadoInvitacion());
    }
}
