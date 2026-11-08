<?php

namespace Tests\Feature\Guia;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Guía de uso en la plataforma: contenido según el tipo de cuenta.
 */
class GuiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_administrador_ve_la_guia_de_dasti(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::query()->where('email', 'admin@dasti.test')->sole();

        $this->actingAs($admin)->get(route('guia'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('guia/Index')
                ->where('ambito', 'admin'));
    }

    public function test_el_cliente_ve_la_guia_de_su_negocio(): void
    {
        $this->seed(DemoSeeder::class);
        $cliente = User::query()->where('rol', 'cliente')->firstOrFail();

        $this->actingAs($cliente)->get(route('guia'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('guia/Index')
                ->where('ambito', 'cliente'));
    }

    public function test_un_invitado_es_enviado_al_inicio_de_sesion(): void
    {
        $this->get(route('guia'))->assertRedirect(route('login'));
    }
}
