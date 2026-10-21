<?php

namespace Tests\Feature\Admin;

use App\Enums\EstadoPreparacion;
use App\Enums\EstadoProducto;
use App\Enums\OrigenCambio;
use App\Models\CambioDestino;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\RegistroAuditoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ProductosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_registrar_producto_genera_enlace_permanente_unico_y_dificil_de_adivinar(): void
    {
        $negocio = Negocio::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.productos.store'), [
            'negocio_id' => $negocio->id,
            'nombre' => 'Tarjeta NFC mostrador',
            'tipo' => 'nfc',
            'url_destino' => 'https://www.instagram.com/negocio',
            'estado_preparacion' => 'pendiente',
            'cantidad' => 1,
        ])->assertSessionHasNoErrors();

        $producto = Producto::query()->sole();
        $this->assertMatchesRegularExpression('/^[23456789abcdefghjkmnpqrstuvwxyz]{10}$/', $producto->codigo);
        $this->assertNotSame((string) $producto->id, $producto->codigo);
        $this->assertSame('https://redireccion.test/r/'.$producto->codigo, $producto->url_permanente);
        $this->assertSame(EstadoProducto::Activo, $producto->estado);

        $cambio = CambioDestino::query()->sole();
        $this->assertNull($cambio->url_anterior);
        $this->assertSame('https://www.instagram.com/negocio', $cambio->url_nueva);
        $this->assertSame(OrigenCambio::Admin, $cambio->origen);
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'producto.creado', 'negocio_id' => $negocio->id]);
    }

    public function test_alta_por_lote_con_codigos_distintos(): void
    {
        $negocio = Negocio::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.productos.store'), [
            'negocio_id' => $negocio->id,
            'nombre' => 'Tarjeta',
            'tipo' => 'nfc',
            'estado_preparacion' => 'pendiente',
            'cantidad' => 3,
        ])->assertRedirect(route('admin.negocios.show', $negocio));

        $productos = Producto::query()->orderBy('id')->get();
        $this->assertSame(['Tarjeta 1', 'Tarjeta 2', 'Tarjeta 3'], $productos->pluck('nombre')->all());
        $this->assertCount(3, $productos->pluck('codigo')->unique());
        $this->assertSame(0, CambioDestino::query()->count(), 'Sin destino inicial no hay historial.');
    }

    public function test_rechaza_destinos_inseguros_y_campos_no_permitidos(): void
    {
        $negocio = Negocio::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.productos.store'), [
            'negocio_id' => $negocio->id,
            'nombre' => 'X',
            'tipo' => 'laser',
            'url_destino' => 'javascript:alert(1)',
            'estado_preparacion' => 'pendiente',
            'cantidad' => 99,
        ])->assertSessionHasErrors(['tipo', 'url_destino', 'cantidad']);

        $this->assertSame(0, Producto::query()->count());
    }

    public function test_la_edicion_administrativa_no_altera_codigo_ni_propietario(): void
    {
        $producto = Producto::factory()->create();
        $otro = Negocio::factory()->create();
        $codigo = $producto->codigo;

        $this->actingAs($this->admin)->put(route('admin.productos.update', $producto), [
            'nombre' => 'Nuevo nombre',
            'tipo' => 'combinado',
            'estado_preparacion' => 'entregado',
            'fecha_entrega' => '2026-10-01',
            'codigo' => 'hackeado22',
            'negocio_id' => $otro->id,
            'url_destino' => 'https://evil.example.org',
        ])->assertSessionHasNoErrors();

        $producto->refresh();
        $this->assertSame('Nuevo nombre', $producto->nombre);
        $this->assertSame(EstadoPreparacion::Entregado, $producto->estado_preparacion);
        $this->assertSame($codigo, $producto->codigo);
        $this->assertNotSame($otro->id, $producto->negocio_id);
        $this->assertNotSame('https://evil.example.org', $producto->url_destino);
    }

    public function test_cambio_de_destino_conserva_historial_y_url_permanente(): void
    {
        $producto = Producto::factory()->create(['url_destino' => 'https://example.com/a']);
        $permanente = $producto->url_permanente;

        $this->actingAs($this->admin)->put(route('admin.productos.destino', $producto), ['url_destino' => 'https://example.com/b'])
            ->assertSessionHasNoErrors();

        $producto->refresh();
        $this->assertSame('https://example.com/b', $producto->url_destino);
        $this->assertSame($permanente, $producto->url_permanente);
        $this->assertDatabaseHas('cambios_destino', ['producto_id' => $producto->id, 'url_anterior' => 'https://example.com/a', 'url_nueva' => 'https://example.com/b']);
        $this->get('/r/'.$producto->codigo)->assertRedirect('https://example.com/b');

        // El administrador puede vaciar el destino.
        $this->actingAs($this->admin)->put(route('admin.productos.destino', $producto), ['url_destino' => ''])->assertSessionHasNoErrors();
        $this->assertNull($producto->fresh()->url_destino);
    }

    public function test_suspender_y_reactivar_producto(): void
    {
        $producto = Producto::factory()->create();

        $this->actingAs($this->admin)->patch(route('admin.productos.estado', $producto), ['estado' => 'suspendido'])->assertSessionHasNoErrors();
        $this->get('/r/'.$producto->codigo)->assertStatus(410);

        $this->actingAs($this->admin)->patch(route('admin.productos.estado', $producto), ['estado' => 'activo']);
        $this->get('/r/'.$producto->codigo)->assertStatus(302);

        $this->assertSame(['producto.suspendido', 'producto.reactivado'], RegistroAuditoria::query()->orderBy('id')->pluck('accion')->all());
    }

    public function test_transferencia_auditada_quita_el_acceso_al_negocio_anterior(): void
    {
        $origen = Negocio::factory()->create();
        $destino = Negocio::factory()->create();
        $clienteOrigen = User::factory()->cliente($origen)->create();
        $clienteDestino = User::factory()->cliente($destino)->create();
        $producto = Producto::factory()->for($origen)->create(['opciones_qr' => ['incluir_logo' => true]]);

        $this->actingAs($this->admin)->post(route('admin.productos.transferir', $producto), [
            'negocio_id' => $destino->id,
            'motivo' => 'Reasignación por venta del local',
            'conservar_destino' => false,
        ])->assertSessionHasNoErrors();

        $producto->refresh();
        $this->assertSame($destino->id, $producto->negocio_id);
        $this->assertNull($producto->url_destino);
        $this->assertFalse($producto->opciones_qr['incluir_logo']);

        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'producto.transferido', 'negocio_id' => $origen->id]);
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'producto.recibido', 'negocio_id' => $destino->id]);

        $this->actingAs($clienteOrigen)->get(route('cliente.productos.show', $producto))->assertNotFound();
        $this->actingAs($clienteOrigen)->put(route('cliente.productos.destino', $producto), ['url_destino' => 'https://example.com'])->assertNotFound();
        $this->actingAs($clienteDestino)->get(route('cliente.productos.show', $producto))->assertOk();
    }

    public function test_no_se_puede_transferir_al_mismo_negocio(): void
    {
        $producto = Producto::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.productos.transferir', $producto), [
            'negocio_id' => $producto->negocio_id,
            'motivo' => 'Prueba de validación',
        ])->assertSessionHasErrors('negocio_id');
    }

    public function test_solo_se_eliminan_productos_pendientes_y_sin_uso(): void
    {
        $entregado = Producto::factory()->create(['estado_preparacion' => EstadoPreparacion::Entregado]);
        $pendiente = Producto::factory()->create(['estado_preparacion' => EstadoPreparacion::Pendiente]);

        $this->actingAs($this->admin)->delete(route('admin.productos.destroy', $entregado))->assertSessionHasErrors('producto');
        $this->assertNotNull($entregado->fresh());

        $this->actingAs($this->admin)->delete(route('admin.productos.destroy', $pendiente))->assertSessionHasNoErrors();
        $this->assertNull($pendiente->fresh());
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'producto.eliminado']);
    }

    public function test_listado_global_con_filtros(): void
    {
        $negocio = Negocio::factory()->create();
        Producto::factory()->for($negocio)->create(['nombre' => 'NFC gerente']);
        Producto::factory()->qr()->for($negocio)->create(['nombre' => 'QR menú']);
        Producto::factory()->qr()->suspendido()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.productos.index', ['negocio_id' => $negocio->id, 'tipo' => 'qr']))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('admin/productos/Index')
                ->has('productos.data', 1)
                ->where('productos.data.0.nombre', 'QR menú')
                ->has('productos.data.0.notas_internas'));
    }
}
