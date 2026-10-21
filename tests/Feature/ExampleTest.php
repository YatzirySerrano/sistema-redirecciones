<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_la_raiz_envia_a_los_invitados_al_inicio_de_sesion(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_no_existe_registro_publico(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'X', 'email' => 'x@y.test', 'password' => 'secreto123', 'password_confirmation' => 'secreto123'])
            ->assertStatus(404);
    }
}
