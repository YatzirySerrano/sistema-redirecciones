<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertOk();
    }

    public function test_el_administrador_puede_actualizar_nombre_y_correo(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => 'Test User', 'email' => 'test@example.com'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
    }

    public function test_el_cliente_solo_puede_cambiar_su_nombre(): void
    {
        $cliente = User::factory()->cliente()->create(['email' => 'original@cliente.test']);

        $this->actingAs($cliente)
            ->patch(route('profile.update'), ['name' => 'Nuevo Nombre', 'email' => 'otro@cliente.test'])
            ->assertSessionHasNoErrors();

        $cliente->refresh();
        $this->assertSame('Nuevo Nombre', $cliente->name);
        $this->assertSame('original@cliente.test', $cliente->email);
    }

    public function test_los_usuarios_no_pueden_eliminar_su_propia_cuenta(): void
    {
        $user = User::factory()->cliente()->create();

        $this->actingAs($user)->delete('/settings/profile', ['password' => 'password'])->assertStatus(405);
        $this->assertNotNull($user->fresh());
    }
}
