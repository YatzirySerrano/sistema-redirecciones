<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_la_raiz_muestra_la_portada_publica_a_los_invitados(): void
    {
        $this->withoutVite();

        $this->get(route('home'))->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->component('Bienvenida'));
    }

    public function test_no_existe_registro_publico(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'X', 'email' => 'x@y.test', 'password' => 'secreto123', 'password_confirmation' => 'secreto123'])
            ->assertStatus(404);
    }
}
