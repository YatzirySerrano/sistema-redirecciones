<?php

namespace Tests\Feature\Admin;

use App\Enums\EstadoCuenta;
use App\Models\Negocio;
use App\Models\User;
use App\Notifications\InvitacionAcceso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UsuariosTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_acceso_de_cliente_para_un_negocio(): void
    {
        $admin = User::factory()->create();
        $negocio = Negocio::factory()->create();

        $this->actingAs($admin)->post(route('admin.usuarios.store'), [
            'name' => 'Segundo usuario',
            'email' => 'segundo@negocio.test',
            'rol' => 'cliente',
            'negocio_id' => $negocio->id,
            'metodo' => 'temporal',
            'password' => 'Temporal-123',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.negocios.show', $negocio));

        $this->assertSame($negocio->id, User::query()->where('email', 'segundo@negocio.test')->value('negocio_id'));
    }

    public function test_un_cliente_requiere_negocio_activo(): void
    {
        $admin = User::factory()->create();
        $suspendido = Negocio::factory()->suspendido()->create();

        $this->actingAs($admin)->post(route('admin.usuarios.store'), [
            'name' => 'X', 'email' => 'x@x.test', 'rol' => 'cliente', 'negocio_id' => $suspendido->id, 'metodo' => 'invitacion',
        ])->assertSessionHasErrors('negocio_id');
    }

    public function test_suspender_usuario_y_proteger_al_ultimo_administrador(): void
    {
        $admin = User::factory()->create();
        $cliente = User::factory()->cliente()->create();

        $this->actingAs($admin)->patch(route('admin.usuarios.estado', $cliente), ['estado' => 'suspendido'])->assertSessionHasNoErrors();
        $this->assertSame(EstadoCuenta::Suspendido, $cliente->fresh()->estado);

        $this->actingAs($admin)->patch(route('admin.usuarios.estado', $admin), ['estado' => 'suspendido'])->assertSessionHasErrors('usuario');
        $this->assertSame(EstadoCuenta::Activo, $admin->fresh()->estado);
    }

    public function test_reenviar_invitacion_y_contrasena_temporal(): void
    {
        Notification::fake();
        $admin = User::factory()->create();
        $cliente = User::factory()->cliente()->create();

        $this->actingAs($admin)->post(route('admin.usuarios.invitacion', $cliente))->assertSessionHasNoErrors();
        Notification::assertSentTo($cliente, InvitacionAcceso::class);

        $this->actingAs($admin)->put(route('admin.usuarios.contrasena', $cliente), ['password' => 'Otra-Temporal-9'])->assertSessionHasNoErrors();
        $cliente->refresh();
        $this->assertTrue(Hash::check('Otra-Temporal-9', $cliente->password));
        $this->assertTrue($cliente->debe_cambiar_password);
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'usuario.contrasena_temporal']);
    }
}
