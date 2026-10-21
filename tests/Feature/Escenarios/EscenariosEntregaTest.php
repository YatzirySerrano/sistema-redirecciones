<?php

namespace Tests\Feature\Escenarios;

use App\Enums\EstadoProducto;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Escenarios de aceptación de punta a punta a través de HTTP.
 */
class EscenariosEntregaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Escenario A: alta y entrega. El administrador da de alta el negocio,
     * registra tres tarjetas con destino y entrega credenciales; el cliente
     * entra, cambia su contraseña temporal y ve solo sus tres tarjetas.
     */
    public function test_escenario_a_alta_y_entrega(): void
    {
        $admin = User::factory()->create(['password' => 'Admin-Clave-1']);
        Producto::factory()->count(2)->create(); // productos de otros negocios

        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'Admin-Clave-1'])->assertRedirect(route('inicio'));
        $this->get(route('inicio'))->assertRedirect(route('admin.panel'));

        $this->post(route('admin.negocios.store'), [
            'nombre_comercial' => 'Cafetería Prueba',
            'estado' => 'activo',
            'crear_acceso' => true,
            'acceso_nombre' => 'Dueña',
            'acceso_email' => 'duena@cafeteria.test',
            'acceso_metodo' => 'temporal',
            'acceso_password' => 'Temporal-2026',
        ])->assertSessionHasNoErrors();
        $negocio = Negocio::query()->where('nombre_comercial', 'Cafetería Prueba')->sole();

        $this->post(route('admin.productos.store'), [
            'negocio_id' => $negocio->id,
            'nombre' => 'Tarjeta NFC',
            'tipo' => 'nfc',
            'url_destino' => 'https://www.instagram.com/cafeteria.prueba',
            'estado_preparacion' => 'entregado',
            'cantidad' => 3,
        ])->assertSessionHasNoErrors();

        $this->post(route('logout'));
        $this->assertGuest();

        $this->post(route('login.store'), ['email' => 'duena@cafeteria.test', 'password' => 'Temporal-2026']);
        $this->get(route('cliente.productos.index'))->assertRedirect(route('contrasena-inicial.edit'));

        $this->put(route('contrasena-inicial.update'), [
            'current_password' => 'Temporal-2026',
            'password' => 'Mi-Clave-Segura-1',
            'password_confirmation' => 'Mi-Clave-Segura-1',
        ])->assertSessionHasNoErrors()->assertRedirect(route('inicio'));

        $this->get(route('cliente.productos.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('productos.data', 3)
                ->where('total_productos', 3)
                ->where('productos.data.0.url_destino', 'https://www.instagram.com/cafeteria.prueba'));
    }

    /**
     * Escenario B y F: el cliente cambia el destino; la URL permanente (la
     * grabada en NFC o impresa en el QR) no cambia y una nueva solicitud
     * pública lleva al nuevo destino.
     */
    public function test_escenario_b_y_f_cambio_de_destino(): void
    {
        $negocio = Negocio::factory()->create();
        $cliente = User::factory()->cliente($negocio)->create();
        $producto = Producto::factory()->for($negocio)->create(['url_destino' => 'https://example.com/anterior']);
        $permanente = $producto->url_permanente;

        $this->get('/r/'.$producto->codigo)->assertRedirect('https://example.com/anterior');

        $this->actingAs($cliente)
            ->put(route('cliente.productos.destino', $producto), ['url_destino' => 'https://example.com/nuevo'])
            ->assertSessionHasNoErrors();

        $producto->refresh();
        $this->assertSame($permanente, $producto->url_permanente);
        $this->get('/r/'.$producto->codigo)->assertRedirect('https://example.com/nuevo');
        $this->assertDatabaseHas('cambios_destino', [
            'producto_id' => $producto->id,
            'user_id' => $cliente->id,
            'origen' => 'cliente',
            'url_anterior' => 'https://example.com/anterior',
            'url_nueva' => 'https://example.com/nuevo',
        ]);
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'producto.destino_cambiado', 'user_id' => $cliente->id]);
    }

    public function test_el_cliente_debe_indicar_un_destino_valido(): void
    {
        $negocio = Negocio::factory()->create();
        $cliente = User::factory()->cliente($negocio)->create();
        $producto = Producto::factory()->for($negocio)->create();

        $this->actingAs($cliente)->put(route('cliente.productos.destino', $producto), ['url_destino' => ''])->assertSessionHasErrors('url_destino');
        $this->actingAs($cliente)->put(route('cliente.productos.destino', $producto), ['url_destino' => 'javascript:alert(1)'])->assertSessionHasErrors('url_destino');
        $this->actingAs($cliente)->put(route('cliente.productos.destino', $producto), ['url_destino' => 'https://redireccion.test/r/'.$producto->codigo])->assertSessionHasErrors('url_destino');
    }

    /**
     * Escenario D: suspensión. El cliente no puede cambiar el destino y el
     * enlace público muestra el estado de no disponible.
     */
    public function test_escenario_d_suspension(): void
    {
        $admin = User::factory()->create();
        $negocio = Negocio::factory()->create();
        $cliente = User::factory()->cliente($negocio)->create();
        $producto = Producto::factory()->for($negocio)->create();

        $this->actingAs($admin)->patch(route('admin.productos.estado', $producto), ['estado' => 'suspendido']);
        $this->assertSame(EstadoProducto::Suspendido, $producto->fresh()->estado);

        $this->actingAs($cliente)->get(route('cliente.productos.show', $producto))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('producto.data.permisos.cambiar_destino', false));
        $this->actingAs($cliente)->put(route('cliente.productos.destino', $producto), ['url_destino' => 'https://example.com/z'])->assertForbidden();

        $this->get('/r/'.$producto->codigo)->assertStatus(410)->assertSee('no está disponible');
    }
}
