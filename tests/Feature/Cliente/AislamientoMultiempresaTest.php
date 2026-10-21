<?php

namespace Tests\Feature\Cliente;

use App\Models\Negocio;
use App\Models\Producto;
use App\Models\User;
use App\Services\Configuracion\AjustesPlataforma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Escenario C: un cliente nunca puede consultar ni modificar recursos de
 * otro negocio, aunque manipule identificadores o solicitudes.
 */
class AislamientoMultiempresaTest extends TestCase
{
    use RefreshDatabase;

    private User $clienteA;

    private Producto $productoA;

    private Producto $productoB;

    protected function setUp(): void
    {
        parent::setUp();

        $negocioA = Negocio::factory()->create();
        $negocioB = Negocio::factory()->create();
        $this->clienteA = User::factory()->cliente($negocioA)->create();
        User::factory()->cliente($negocioB)->create();
        $this->productoA = Producto::factory()->for($negocioA)->create(['url_destino' => 'https://example.com/a']);
        $this->productoB = Producto::factory()->for($negocioB)->create(['url_destino' => 'https://example.com/b']);
    }

    public function test_el_cliente_a_no_ve_el_producto_del_cliente_b(): void
    {
        $this->actingAs($this->clienteA)->get(route('cliente.productos.show', $this->productoB))->assertNotFound();
    }

    public function test_el_cliente_a_no_puede_modificar_el_destino_del_cliente_b(): void
    {
        $this->actingAs($this->clienteA)
            ->put(route('cliente.productos.destino', $this->productoB), ['url_destino' => 'https://atacante.example.org'])
            ->assertNotFound();

        $this->assertSame('https://example.com/b', $this->productoB->fresh()->url_destino);
        $this->assertDatabaseMissing('cambios_destino', ['producto_id' => $this->productoB->id]);
    }

    public function test_el_cliente_no_puede_usar_rutas_de_administrador_ni_el_qr_ajeno(): void
    {
        $this->actingAs($this->clienteA)->put(route('admin.productos.destino', $this->productoA), ['url_destino' => 'https://example.com/x'])->assertForbidden();
        $this->actingAs($this->clienteA)->get(route('productos.qr.show', $this->productoB))->assertNotFound();
        $this->actingAs($this->clienteA)->put(route('productos.qr.update', $this->productoB), ['color' => '#000000'])->assertNotFound();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function rutasAdministrativas(): array
    {
        return [
            'panel' => ['get', 'admin.panel'],
            'negocios' => ['get', 'admin.negocios.index'],
            'crear negocio' => ['post', 'admin.negocios.store'],
            'productos' => ['get', 'admin.productos.index'],
            'crear producto' => ['post', 'admin.productos.store'],
            'usuarios' => ['get', 'admin.usuarios.index'],
            'auditoría' => ['get', 'admin.auditoria.index'],
            'estadísticas' => ['get', 'admin.estadisticas.index'],
            'configuración' => ['put', 'admin.configuracion.update'],
            'redirecciones' => ['get', 'admin.enlaces.index'],
        ];
    }

    #[DataProvider('rutasAdministrativas')]
    public function test_las_rutas_administrativas_estan_cerradas_para_clientes(string $metodo, string $ruta): void
    {
        $this->actingAs($this->clienteA)->{$metodo}(route($ruta))->assertForbidden();
    }

    public function test_el_listado_solo_incluye_productos_propios(): void
    {
        $this->actingAs($this->clienteA)->get(route('cliente.productos.index'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('cliente/productos/Index')
                ->has('productos.data', 1)
                ->where('productos.data.0.id', $this->productoA->id)
                ->missing('productos.data.0.notas_internas')
                ->missing('productos.data.0.referencia_comercial'));
    }

    public function test_el_cliente_no_puede_cambiar_propiedad_ni_codigo_con_parametros_extra(): void
    {
        $negocioB = $this->productoB->negocio_id;
        $codigo = $this->productoA->codigo;

        $this->actingAs($this->clienteA)->put(route('cliente.productos.destino', $this->productoA), [
            'url_destino' => 'https://example.com/nuevo',
            'negocio_id' => $negocioB,
            'codigo' => 'propio2222',
            'estado' => 'activo',
        ])->assertSessionHasNoErrors();

        $this->productoA->refresh();
        $this->assertSame('https://example.com/nuevo', $this->productoA->url_destino);
        $this->assertSame($codigo, $this->productoA->codigo);
        $this->assertNotSame($negocioB, $this->productoA->negocio_id);
    }

    public function test_las_estadisticas_del_cliente_excluyen_otros_negocios(): void
    {
        DB::table('accesos_diarios')->insert([
            ['producto_id' => $this->productoA->id, 'negocio_id' => $this->productoA->negocio_id, 'fecha' => now()->toDateString(), 'total' => 5, 'bots' => 0],
            ['producto_id' => $this->productoB->id, 'negocio_id' => $this->productoB->negocio_id, 'fecha' => now()->toDateString(), 'total' => 40, 'bots' => 0],
        ]);

        $this->actingAs($this->clienteA)->get(route('cliente.estadisticas.index', ['producto_id' => $this->productoB->id]))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('cliente/estadisticas/Index')
                ->where('totales.total', 5)
                ->where('filtros.producto_id', '')
                ->has('productos_top', 1));
    }

    public function test_las_estadisticas_se_pueden_deshabilitar_para_clientes(): void
    {
        app(AjustesPlataforma::class)->guardar(['clientes_ven_estadisticas' => false]);

        $this->actingAs($this->clienteA)->get(route('cliente.estadisticas.index'))->assertForbidden();
    }
}
