<?php

namespace Tests\Feature\Servicios;

use App\Models\Negocio;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Autorizacion\CatalogoPermisos;
use App\Services\Autorizacion\SincronizadorPermisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * «servicios.ver» en cuentas de negocio: controla si el cliente ve y puede
 * usar «Mis servicios» (catálogo y contratación en línea). Política inicial:
 * ningún rol de cliente lo tiene hasta que DASTI lo habilite. Sin él, los
 * productos QR/NFC siguen funcionando con normalidad.
 */
class VisibilidadServiciosTest extends TestCase
{
    use RefreshDatabase;

    private Negocio $negocio;

    private User $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->negocio = Negocio::factory()->create(['telefono' => '5512345678']);
        $this->cliente = User::factory()->cliente($this->negocio)->create();
    }

    private function habilitar(): void
    {
        Rol::porClave(CatalogoPermisos::ROL_CLIENTE)?->givePermissionTo('servicios.ver');
    }

    public function test_el_permiso_existe_una_sola_vez_y_admite_ambos_ambitos(): void
    {
        $this->assertTrue(CatalogoPermisos::admiteAmbito('servicios.ver', CatalogoPermisos::AMBITO_NEGOCIO));
        $this->assertTrue(CatalogoPermisos::admiteAmbito('servicios.ver', CatalogoPermisos::AMBITO_PLATAFORMA));
        $this->assertSame(1, Permission::query()->where('name', 'servicios.ver')->count());

        // Sincronizar de nuevo no lo duplica ni lo asigna a clientes.
        app(SincronizadorPermisos::class)->sincronizar();
        $this->assertSame(1, Permission::query()->where('name', 'servicios.ver')->count());
        $this->assertNotContains('servicios.ver', Rol::porClave(CatalogoPermisos::ROL_CLIENTE)?->load('permissions')->nombresPermisos() ?? []);
        $this->assertNotContains('servicios.ver', CatalogoPermisos::predeterminados(CatalogoPermisos::ROL_CLIENTE));
        $this->assertContains('servicios.ver', Rol::porClave(CatalogoPermisos::ROL_ADMINISTRADOR)?->load('permissions')->nombresPermisos() ?? []);
    }

    public function test_sin_el_permiso_el_cliente_no_ve_ni_abre_mis_servicios(): void
    {
        $this->actingAs($this->cliente)->get(route('cliente.inicio'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('auth.permisos.lista', fn ($lista) => ! collect($lista)->contains('servicios.ver')));

        $this->actingAs($this->cliente)->get(route('cliente.servicios.index'))->assertForbidden();
        $this->actingAs($this->cliente)->get(route('cliente.servicios.show', Servicio::FIDELIDAD))->assertForbidden();
        $this->actingAs($this->cliente)->get(route('cliente.servicios.retorno', ['folio' => 'X']))->assertForbidden();
        // Aunque tenga «servicios.contratar», no puede iniciar un pago.
        $this->assertTrue($this->cliente->fresh()->tienePermiso('servicios.contratar'));
        $this->actingAs($this->cliente)->post(route('cliente.servicios.pago', Servicio::FIDELIDAD))->assertForbidden();
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_sin_el_permiso_los_productos_siguen_funcionando(): void
    {
        $producto = Producto::factory()->for($this->negocio)->create(['url_destino' => 'https://www.example.org/menu']);

        $this->get('/'.config('dasti.prefijo_redireccion').'/'.$producto->codigo)
            ->assertRedirect('https://www.example.org/menu');
        $this->actingAs($this->cliente)->get(route('cliente.productos.index'))->assertOk();
        $this->actingAs($this->cliente)->get(route('cliente.productos.show', $producto))->assertOk();
        $this->actingAs($this->cliente)->get(route('cliente.estadisticas.index'))->assertOk();
    }

    public function test_un_modulo_no_contratado_sin_el_permiso_vuelve_al_inicio(): void
    {
        $this->actingAs($this->cliente)->get(route('cliente.fidelidad.inicio'))
            ->assertRedirect(route('cliente.inicio'));

        $this->habilitar();
        $this->actingAs($this->cliente->fresh())->get(route('cliente.fidelidad.inicio'))
            ->assertRedirect(route('cliente.servicios.show', Servicio::FIDELIDAD));
    }

    public function test_con_el_permiso_el_cliente_ve_mis_servicios(): void
    {
        $this->habilitar();

        $this->actingAs($this->cliente)->get(route('cliente.servicios.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('cliente/servicios/Index'));
        $this->actingAs($this->cliente)->get(route('cliente.servicios.show', Servicio::FIDELIDAD))->assertOk();
    }

    public function test_retirar_el_permiso_aplica_en_la_siguiente_peticion_de_una_sesion_activa(): void
    {
        $this->habilitar();
        $this->actingAs($this->cliente)->get(route('cliente.servicios.index'))->assertOk();

        $super = User::factory()->superadministrador()->create();
        $rol = Rol::porClave(CatalogoPermisos::ROL_CLIENTE);
        $this->assertNotNull($rol);
        $permisos = array_values(array_diff($rol->load('permissions')->nombresPermisos(), ['servicios.ver']));

        $this->actingAs($super)->put(route('admin.roles.update', $rol), [
            'name' => $rol->name,
            'descripcion' => $rol->descripcion,
            'permisos' => $permisos,
        ])->assertSessionHasNoErrors();

        // Misma sesión del cliente, nueva petición: el permiso ya no aplica.
        $this->actingAs($this->cliente->fresh())->get(route('cliente.servicios.index'))->assertForbidden();
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'rol.actualizado', 'auditable_id' => $rol->id]);
    }

    public function test_un_administrador_puede_habilitar_el_modulo_desde_roles_y_permisos(): void
    {
        $super = User::factory()->superadministrador()->create();
        $rol = Rol::porClave(CatalogoPermisos::ROL_CLIENTE);
        $this->assertNotNull($rol);

        $this->actingAs($super)->get(route('admin.roles.edit', $rol))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('matriz', fn ($modulos) => collect($modulos)
                ->firstWhere('clave', 'servicios')['permisos'][0]['nombre'] === 'servicios.ver'));

        $this->actingAs($super)->put(route('admin.roles.update', $rol), [
            'name' => $rol->name,
            'descripcion' => $rol->descripcion,
            'permisos' => [...$rol->load('permissions')->nombresPermisos(), 'servicios.ver'],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->cliente->fresh())->get(route('cliente.servicios.index'))->assertOk();
    }

    public function test_un_permiso_directo_al_usuario_no_otorga_acceso(): void
    {
        // Los permisos se resuelven por rol: una asignación directa (fuera
        // de la interfaz) no habilita el módulo.
        $this->cliente->givePermissionTo('servicios.ver');

        $this->actingAs($this->cliente->fresh())->get(route('cliente.servicios.index'))->assertForbidden();
    }

    public function test_el_personal_dasti_conserva_la_administracion_de_servicios(): void
    {
        $admin = User::factory()->administrador()->conRol(CatalogoPermisos::ROL_ADMINISTRADOR)->create();

        $this->actingAs($admin)->get(route('admin.servicios.index'))->assertOk();
        // Las rutas del cliente siguen siendo exclusivas de cuentas de negocio.
        $this->actingAs($admin)->get(route('cliente.servicios.index'))->assertForbidden();
    }
}
