<?php

namespace Tests\Feature\Productos;

use App\Enums\EstadoProducto;
use App\Models\CambioDestino;
use App\Models\EventoAcceso;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Productos QR + NFC con destinos independientes (frente NFC / reverso QR).
 */
class DestinosIndependientesTest extends TestCase
{
    use RefreshDatabase;

    private const UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    private User $admin;

    private Negocio $negocio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->negocio = Negocio::factory()->create();
    }

    private function crearIndependiente(array $extra = []): Producto
    {
        $this->actingAs($this->admin)->post(route('admin.productos.store'), [
            'negocio_id' => $this->negocio->id,
            'nombre' => 'Tarjeta doble',
            'tipo' => 'combinado',
            'modalidad_destino' => 'independiente',
            'url_destino' => 'https://www.tiktok.com/@negocio',
            'url_destino_qr' => 'https://www.instagram.com/negocio',
            'estado_preparacion' => 'pendiente',
            'cantidad' => 1,
            ...$extra,
        ])->assertSessionHasNoErrors();

        return Producto::query()->principales()->where('negocio_id', $this->negocio->id)->latest('id')->firstOrFail();
    }

    public function test_crea_un_producto_fisico_con_dos_enlaces_y_codigos_distintos(): void
    {
        $producto = $this->crearIndependiente();
        $reverso = $producto->enlacesSecundarios()->sole();

        $this->assertTrue($producto->tieneDestinosIndependientes());
        $this->assertSame('nfc', $producto->medio?->value);
        $this->assertSame('qr', $reverso->medio?->value);
        $this->assertNotSame($producto->codigo, $reverso->codigo);
        $this->assertSame('https://www.tiktok.com/@negocio', $producto->url_destino);
        $this->assertSame('https://www.instagram.com/negocio', $reverso->url_destino);

        // Cuenta como una sola tarjeta vendida.
        $this->assertSame(1, Negocio::query()->conResumen()->findOrFail($this->negocio->id)->productos_count);
        $this->actingAs($this->admin)->get(route('admin.productos.index'))
            ->assertInertia(fn (Assert $page) => $page->where('productos.meta.total', 1)
                ->where('productos.data.0.enlaces_secundarios.0.medio', 'qr'));
    }

    public function test_cada_medio_redirige_a_su_destino_con_estadisticas_e_historial_propios(): void
    {
        $producto = $this->crearIndependiente();
        $reverso = $producto->enlacesSecundarios()->sole();

        $this->withHeader('User-Agent', self::UA)->get('/r/'.$producto->codigo)->assertRedirect('https://www.tiktok.com/@negocio');
        $this->withHeader('User-Agent', self::UA)->get('/r/'.$reverso->codigo)->assertRedirect('https://www.instagram.com/negocio');
        $this->withHeader('User-Agent', self::UA)->get('/r/'.$reverso->codigo)->assertStatus(302);

        $this->assertSame(1, EventoAcceso::query()->where('producto_id', $producto->id)->count());
        $this->assertSame(2, EventoAcceso::query()->where('producto_id', $reverso->id)->count());

        // Cambiar el destino del QR no altera el del NFC.
        $this->actingAs($this->admin)->put(route('admin.productos.destino', $reverso), ['url_destino' => 'https://wa.me/5215512345678'])
            ->assertSessionHasNoErrors();
        $this->assertSame('https://www.tiktok.com/@negocio', $producto->fresh()?->url_destino);
        $this->assertSame(2, CambioDestino::query()->where('producto_id', $reverso->id)->count());
        $this->assertSame(1, CambioDestino::query()->where('producto_id', $producto->id)->count());
    }

    public function test_los_combinados_existentes_conservan_el_destino_compartido(): void
    {
        $this->actingAs($this->admin)->post(route('admin.productos.store'), [
            'negocio_id' => $this->negocio->id, 'nombre' => 'Compartido', 'tipo' => 'combinado',
            'url_destino' => 'https://www.example.org', 'estado_preparacion' => 'pendiente', 'cantidad' => 1,
        ])->assertSessionHasNoErrors();

        $producto = Producto::query()->where('nombre', 'Compartido')->sole();
        $this->assertSame('compartido', $producto->modalidad_destino->value);
        $this->assertNull($producto->medio);
        $this->assertSame(0, $producto->enlacesSecundarios()->count());

        // Un producto anterior (sin columnas nuevas informadas) sigue igual.
        $previo = Producto::factory()->for($this->negocio)->create(['tipo' => 'combinado', 'url_destino' => 'https://www.example.net']);
        $this->assertSame('compartido', $previo->fresh()?->modalidad_destino->value);
        $this->get('/r/'.$previo->codigo)->assertRedirect('https://www.example.net');
    }

    public function test_suspender_y_reactivar_aplica_a_ambos_medios(): void
    {
        $producto = $this->crearIndependiente();
        $reverso = $producto->enlacesSecundarios()->sole();

        $this->actingAs($this->admin)->patch(route('admin.productos.estado', $producto), ['estado' => 'suspendido'])->assertSessionHasNoErrors();
        $this->assertSame(EstadoProducto::Suspendido, $reverso->fresh()?->estado);
        $this->get('/r/'.$reverso->codigo)->assertStatus(410);

        $this->actingAs($this->admin)->patch(route('admin.productos.estado', $producto), ['estado' => 'activo'])->assertSessionHasNoErrors();
        $this->assertSame(EstadoProducto::Activo, $reverso->fresh()?->estado);
    }

    public function test_transferir_mueve_ambos_enlaces_y_aisla_al_negocio_anterior(): void
    {
        $producto = $this->crearIndependiente();
        $reverso = $producto->enlacesSecundarios()->sole();
        $anterior = User::factory()->cliente($this->negocio)->create();
        $nuevo = Negocio::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.productos.transferir', $producto), [
            'negocio_id' => $nuevo->id, 'motivo' => 'Cambio de dueño', 'conservar_destino' => false,
        ])->assertSessionHasNoErrors();

        $this->assertSame($nuevo->id, $reverso->fresh()?->negocio_id);
        $this->assertNull($reverso->fresh()?->url_destino);
        $this->assertSame($reverso->codigo, $reverso->fresh()?->codigo);
        $this->actingAs($anterior)->get(route('cliente.productos.show', $reverso))->assertNotFound();
        $this->actingAs($anterior)->put(route('cliente.productos.destino', $reverso), ['url_destino' => 'https://example.org'])->assertNotFound();
    }

    public function test_el_cliente_administra_ambos_destinos_desde_la_ficha(): void
    {
        $producto = $this->crearIndependiente();
        $reverso = $producto->enlacesSecundarios()->sole();
        $cliente = User::factory()->cliente($this->negocio)->create();
        $ajeno = User::factory()->cliente()->create();

        $this->actingAs($cliente)->get(route('cliente.productos.show', $reverso))->assertRedirect(route('cliente.productos.show', $producto));
        $this->actingAs($cliente)->get(route('cliente.productos.show', $producto))
            ->assertInertia(fn (Assert $page) => $page
                ->where('producto.data.modalidad_destino', 'independiente')
                ->where('producto.data.enlaces_secundarios.0.id', $reverso->id)
                ->where('producto.data.enlaces_secundarios.0.permisos.cambiar_destino', true));

        $this->actingAs($cliente)->put(route('cliente.productos.destino', $reverso), ['url_destino' => 'https://www.facebook.com/negocio'])
            ->assertSessionHasNoErrors();
        $this->assertSame('https://www.facebook.com/negocio', $reverso->fresh()?->url_destino);

        $this->actingAs($ajeno)->put(route('cliente.productos.destino', $reverso), ['url_destino' => 'https://example.org'])->assertNotFound();

        // La lista del cliente muestra una sola tarjeta.
        $this->actingAs($cliente)->get(route('cliente.productos.index'))
            ->assertInertia(fn (Assert $page) => $page->where('total_productos', 1)->has('productos.data', 1));
    }

    public function test_el_reverso_redirige_a_la_ficha_del_producto_fisico(): void
    {
        $producto = $this->crearIndependiente();
        $reverso = $producto->enlacesSecundarios()->sole();

        $this->actingAs($this->admin)->get(route('admin.productos.show', $reverso))->assertRedirect(route('admin.productos.show', $producto));
        $this->actingAs($this->admin)->get(route('admin.productos.show', $producto))
            ->assertInertia(fn (Assert $page) => $page->has('estadisticas_medios', 2));
    }

    public function test_no_puede_dejar_de_ser_combinado_y_se_elimina_completo(): void
    {
        $producto = $this->crearIndependiente();
        $reverso = $producto->enlacesSecundarios()->sole();

        $this->actingAs($this->admin)->put(route('admin.productos.update', $producto), [
            'nombre' => 'Otro nombre', 'tipo' => 'nfc', 'estado_preparacion' => 'pendiente',
        ])->assertSessionHasErrors('tipo');

        $this->actingAs($this->admin)->put(route('admin.productos.update', $producto), [
            'nombre' => 'Otro nombre', 'tipo' => 'combinado', 'estado_preparacion' => 'listo',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Otro nombre', $reverso->fresh()?->nombre);

        $this->actingAs($this->admin)->put(route('admin.productos.update', $producto), [
            'nombre' => 'Otro nombre', 'tipo' => 'combinado', 'estado_preparacion' => 'pendiente',
        ]);
        $this->actingAs($this->admin)->delete(route('admin.productos.destroy', $producto))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('productos', ['id' => $reverso->id]);
        $this->assertDatabaseMissing('productos', ['id' => $producto->id]);
    }

    public function test_un_lote_independiente_crea_un_reverso_por_unidad(): void
    {
        $this->crearIndependiente(['cantidad' => 3]);

        $this->assertSame(3, Producto::query()->principales()->where('negocio_id', $this->negocio->id)->count());
        $this->assertSame(3, Producto::query()->whereNotNull('producto_principal_id')->count());
        $this->assertSame(6, Producto::query()->distinct()->count('codigo'));
    }
}
