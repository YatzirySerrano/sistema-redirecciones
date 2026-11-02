<?php

namespace Tests\Feature\Admin;

use App\Models\Negocio;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Vistas «tabla» y «tarjetas»: misma consulta, filtros, paginación y
 * permisos; solo cambia la presentación (preferencia por usuario).
 */
class ListadosTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_preferencia_de_vista_se_guarda_por_usuario(): void
    {
        $admin = User::factory()->create();
        $otro = User::factory()->create();

        $this->actingAs($admin)->putJson(route('preferencias.vista'), ['modulo' => 'productos', 'vista' => 'tarjetas'])
            ->assertOk()->assertJsonPath('vistas.productos', 'tarjetas');
        $this->actingAs($admin)->putJson(route('preferencias.vista'), ['modulo' => 'negocios', 'vista' => 'tabla'])->assertOk();

        $this->assertSame(['productos' => 'tarjetas', 'negocios' => 'tabla'], $admin->fresh()?->preferencias['vistas'] ?? null);

        $this->actingAs($admin)->get(route('admin.productos.index'))
            ->assertInertia(fn (Assert $page) => $page->where('preferencias.vistas.productos', 'tarjetas'));
        $this->actingAs($otro)->get(route('admin.productos.index'))
            ->assertInertia(fn (Assert $page) => $page->missing('preferencias.vistas.productos'));
    }

    public function test_valida_modulo_y_vista(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->putJson(route('preferencias.vista'), ['modulo' => 'otro', 'vista' => 'tabla'])->assertUnprocessable();
        $this->actingAs($admin)->putJson(route('preferencias.vista'), ['modulo' => 'productos', 'vista' => 'mosaico'])->assertUnprocessable();
        auth()->logout();
        $this->putJson(route('preferencias.vista'), ['modulo' => 'productos', 'vista' => 'tabla'])->assertUnauthorized();
    }

    public function test_el_cliente_guarda_la_vista_de_sus_productos(): void
    {
        $cliente = User::factory()->cliente()->create();

        $this->actingAs($cliente)->putJson(route('preferencias.vista'), ['modulo' => 'cliente_productos', 'vista' => 'tabla'])->assertOk();
        $this->actingAs($cliente)->get(route('cliente.productos.index'))
            ->assertInertia(fn (Assert $page) => $page->where('preferencias.vistas.cliente_productos', 'tabla'));
    }

    public function test_los_listados_filtran_buscan_y_paginan_en_el_servidor(): void
    {
        $admin = User::factory()->create();
        $negocio = Negocio::factory()->create();
        Producto::factory()->count(25)->for($negocio)->create();
        Producto::factory()->create(['nombre' => 'Ajeno especial']);

        $this->actingAs($admin)->get(route('admin.productos.index', ['negocio_id' => $negocio->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('productos.data', 20)
                ->where('productos.meta.total', 25)
                ->has('estados'));

        $this->actingAs($admin)->get(route('admin.productos.index', ['buscar' => 'especial']))
            ->assertInertia(fn (Assert $page) => $page->has('productos.data', 1)->where('productos.data.0.nombre', 'Ajeno especial'));

        foreach (['admin.negocios.index', 'admin.usuarios.index', 'admin.enlaces.index', 'admin.roles.index'] as $ruta) {
            $this->actingAs($admin)->get(route($ruta))->assertOk();
        }
    }

    public function test_las_acciones_dependen_de_los_permisos(): void
    {
        $lectura = new Rol;
        $lectura->forceFill(['name' => 'Lectura', 'guard_name' => 'web', 'ambito' => 'plataforma'])->save();
        $lectura->syncPermissions(['productos.ver']);
        $usuario = User::factory()->conRol($lectura)->create();
        Producto::factory()->create();

        $this->actingAs($usuario)->get(route('admin.productos.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permisos.lista', ['productos.ver'])
                ->where('productos.data.0.permisos.cambiar_destino', false)
                ->where('productos.data.0.permisos.descargar_qr', false));

        $admin = User::factory()->create();
        $this->actingAs($admin)->get(route('admin.productos.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('productos.data.0.permisos.cambiar_destino', true)
                ->where('productos.data.0.permisos.descargar_qr', true));
    }

    public function test_filtro_de_usuarios_por_rol(): void
    {
        $admin = User::factory()->create();
        $soporte = new Rol;
        $soporte->forceFill(['name' => 'Soporte', 'guard_name' => 'web', 'ambito' => 'plataforma'])->save();
        $miembro = User::factory()->conRol($soporte)->create();

        $this->actingAs($admin)->get(route('admin.usuarios.index', ['rol_id' => $soporte->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('usuarios.data', 1)
                ->where('usuarios.data.0.id', $miembro->id)
                ->where('usuarios.data.0.rol_asignado.nombre', 'Soporte'));
    }
}
