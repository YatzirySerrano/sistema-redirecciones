<?php

namespace Tests\Feature\Admin;

use App\Models\Negocio;
use App\Models\Producto;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Todas las pantallas se renderizan con datos reales (seeders de demo).
 */
class PantallasAdministrativasTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function pantallas(): array
    {
        return [
            'panel' => ['admin.panel', 'admin/Panel'],
            'negocios' => ['admin.negocios.index', 'admin/negocios/Index'],
            'nuevo negocio' => ['admin.negocios.create', 'admin/negocios/Create'],
            'productos' => ['admin.productos.index', 'admin/productos/Index'],
            'nuevo producto' => ['admin.productos.create', 'admin/productos/Create'],
            'redirecciones' => ['admin.enlaces.index', 'admin/enlaces/Index'],
            'estadísticas' => ['admin.estadisticas.index', 'admin/estadisticas/Index'],
            'auditoría' => ['admin.auditoria.index', 'admin/auditoria/Index'],
            'usuarios' => ['admin.usuarios.index', 'admin/usuarios/Index'],
            'nuevo usuario' => ['admin.usuarios.create', 'admin/usuarios/Create'],
            'configuración' => ['admin.configuracion.edit', 'admin/configuracion/Edit'],
        ];
    }

    #[DataProvider('pantallas')]
    public function test_la_pantalla_se_renderiza(string $ruta, string $componente): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::query()->where('email', 'admin@dasti.test')->sole();

        $this->actingAs($admin)->get(route($ruta))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component($componente));
    }

    public function test_fichas_de_detalle_y_edicion(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::query()->where('email', 'admin@dasti.test')->sole();
        $negocio = Negocio::query()->firstOrFail();
        $producto = Producto::query()->firstOrFail();
        $usuario = User::query()->where('rol', 'cliente')->firstOrFail();

        foreach ([
            route('admin.negocios.show', $negocio) => 'admin/negocios/Show',
            route('admin.negocios.edit', $negocio) => 'admin/negocios/Edit',
            route('admin.productos.show', $producto) => 'admin/productos/Show',
            route('admin.productos.edit', $producto) => 'admin/productos/Edit',
            route('admin.usuarios.edit', $usuario) => 'admin/usuarios/Edit',
        ] as $url => $componente) {
            $this->actingAs($admin)->get($url)->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component($componente));
        }
    }

    public function test_el_panel_muestra_indicadores_reales(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::query()->where('email', 'admin@dasti.test')->sole();

        $this->actingAs($admin)->get(route('admin.panel'))->assertInertia(fn (AssertableInertia $p) => $p
            ->where('indicadores.negocios', Negocio::query()->count())
            ->where('indicadores.productos', Producto::query()->count())
            ->where('indicadores.negocios_activos', 3)
            ->has('serie', 30));
    }

    public function test_pantallas_del_cliente(): void
    {
        $this->seed(DemoSeeder::class);
        $cliente = User::query()->where('email', 'cafe@cliente.test')->sole();
        $producto = Producto::query()->where('negocio_id', $cliente->negocio_id)->firstOrFail();

        $this->actingAs($cliente)->get(route('cliente.inicio'))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component('cliente/Inicio'));
        $this->actingAs($cliente)->get(route('cliente.productos.index'))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->has('productos.data', 5));
        $this->actingAs($cliente)->get(route('cliente.productos.show', $producto))->assertOk();
        $this->actingAs($cliente)->get(route('cliente.estadisticas.index'))->assertOk();
        $this->actingAs($cliente)->get(route('profile.edit'))->assertOk();
    }
}
