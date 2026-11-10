<?php

namespace Tests\Feature\Productos;

use App\Models\Negocio;
use App\Models\Producto;
use App\Models\RegistroAuditoria;
use App\Models\Rol;
use App\Models\User;
use App\Services\Autorizacion\CatalogoPermisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Permiso «productos.editar_nombre»: el cliente cambia solo el nombre
 * descriptivo de sus productos, sin acceso a ningún otro dato.
 */
class EditarNombreProductoTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISO = 'productos.editar_nombre';

    private User $admin;

    /** Gestiona «Roles y permisos» (el administrador no lo hace de inicio). */
    private User $superadmin;

    private Negocio $negocio;

    private User $cliente;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->admin = User::factory()->create();
        $this->superadmin = User::factory()->superadministrador()->create();
        $this->negocio = Negocio::factory()->create();
        $this->cliente = User::factory()->cliente($this->negocio)->create();
        $this->producto = Producto::factory()->for($this->negocio)->create([
            'nombre' => 'Tarjeta 1',
            'tipo' => 'qr',
            'url_destino' => 'https://www.instagram.com/negocio',
            'referencia_comercial' => 'REF-001',
            'notas_internas' => 'Nota interna de DASTI',
        ]);
    }

    private function rolCliente(): Rol
    {
        $rol = Rol::porClave(CatalogoPermisos::ROL_CLIENTE);
        $this->assertNotNull($rol);

        return $rol;
    }

    /**
     * Asigna o retira el permiso desde «Roles y permisos», como lo haría DASTI.
     */
    private function permisoEnRolCliente(bool $otorgar): void
    {
        $rol = $this->rolCliente();
        $permisos = $rol->permissions()->pluck('name')->reject(fn (string $p) => $p === self::PERMISO)->values()->all();

        if ($otorgar) {
            $permisos[] = self::PERMISO;
        }

        $this->actingAs($this->superadmin)->put(route('admin.roles.update', $rol), [
            'name' => $rol->name,
            'descripcion' => $rol->descripcion,
            'permisos' => $permisos,
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.roles.show', $rol));

        $this->assertSame($otorgar, $rol->fresh()?->hasPermissionTo(self::PERMISO));
        // Instancia nueva: los permisos resueltos se guardan por instancia.
        $this->cliente = $this->cliente->fresh() ?? $this->cliente;
    }

    private function renombrar(array $datos, ?User $usuario = null): TestResponse
    {
        return $this->actingAs($usuario ?? $this->cliente)
            ->from(route('cliente.productos.show', $this->producto))
            ->patch(route('cliente.productos.nombre', $this->producto), $datos);
    }

    public function test_el_permiso_es_solo_de_negocio_y_ningun_rol_lo_tiene_de_inicio(): void
    {
        $this->assertTrue(CatalogoPermisos::existe(self::PERMISO));
        $this->assertSame([CatalogoPermisos::AMBITO_NEGOCIO], CatalogoPermisos::definicion(self::PERMISO)['ambitos']);
        $this->assertNotContains(self::PERMISO, CatalogoPermisos::predeterminados(CatalogoPermisos::ROL_CLIENTE));
        $this->assertFalse($this->rolCliente()->hasPermissionTo(self::PERMISO));

        // Aparece en «Roles y permisos» para asignarlo.
        $this->actingAs($this->admin)->get(route('admin.roles.show', $this->rolCliente()))->assertOk()
            ->assertSee(self::PERMISO, false);
    }

    public function test_cliente_con_permiso_cambia_el_nombre_y_se_refleja_en_sus_vistas(): void
    {
        $this->permisoEnRolCliente(true);

        $this->actingAs($this->cliente)->get(route('cliente.productos.show', $this->producto))
            ->assertInertia(fn (Assert $p) => $p->where('producto.data.permisos.editar_nombre', true));

        $this->renombrar(['nombre' => '  Mostrador principal  '])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('cliente.productos.show', $this->producto));

        $this->assertSame('Mostrador principal', $this->producto->fresh()?->nombre);

        $this->actingAs($this->cliente)->get(route('cliente.productos.show', $this->producto))
            ->assertInertia(fn (Assert $p) => $p->where('producto.data.nombre', 'Mostrador principal'));
        $this->actingAs($this->cliente)->get(route('cliente.productos.index'))
            ->assertInertia(fn (Assert $p) => $p->where('productos.data.0.nombre', 'Mostrador principal'));

        // Se registra con el mecanismo de auditoría existente.
        $registro = RegistroAuditoria::query()->where('accion', 'producto.actualizado')->latest('id')->firstOrFail();
        $this->assertSame($this->cliente->id, $registro->user_id);
    }

    public function test_cliente_sin_permiso_no_ve_la_opcion_ni_puede_cambiarlo(): void
    {
        $this->actingAs($this->cliente)->get(route('cliente.productos.show', $this->producto))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('producto.data.permisos.editar_nombre', false));

        $this->renombrar(['nombre' => 'Otro nombre'])->assertForbidden();
        $this->assertSame('Tarjeta 1', $this->producto->fresh()?->nombre);
    }

    public function test_no_puede_renombrar_productos_de_otro_negocio(): void
    {
        $this->permisoEnRolCliente(true);
        $ajeno = User::factory()->cliente(Negocio::factory()->create())->create();

        $this->renombrar(['nombre' => 'Intruso'], $ajeno)->assertNotFound();
        $this->assertSame('Tarjeta 1', $this->producto->fresh()?->nombre);
    }

    public function test_sin_sesion_no_se_puede_renombrar(): void
    {
        $this->patch(route('cliente.productos.nombre', $this->producto), ['nombre' => 'Anónimo'])
            ->assertRedirect(route('login'));
        $this->assertSame('Tarjeta 1', $this->producto->fresh()?->nombre);
    }

    public function test_los_campos_adicionales_se_ignoran(): void
    {
        $this->permisoEnRolCliente(true);
        $otro = Negocio::factory()->create();
        $antes = $this->producto->fresh()?->getAttributes();
        $this->assertIsArray($antes);

        $this->renombrar([
            'nombre' => 'Reseñas Google',
            'codigo' => 'hackeado01',
            'tipo' => 'nfc',
            'negocio_id' => $otro->id,
            'url_destino' => 'https://malicioso.example',
            'estado' => 'suspendido',
            'estado_preparacion' => 'entregado',
            'fecha_entrega' => '2030-01-01',
            'referencia_comercial' => 'X',
            'notas_internas' => 'X',
            'cantidad_qr' => 2,
            'total_accesos' => 999,
            'id' => 999999,
        ])->assertSessionHasNoErrors();

        $despues = $this->producto->fresh()?->getAttributes();
        $this->assertIsArray($despues);
        $this->assertSame('Reseñas Google', $despues['nombre']);
        unset($antes['nombre'], $antes['updated_at'], $despues['nombre'], $despues['updated_at']);
        $this->assertEquals($antes, $despues, 'Solo cambia el nombre.');
    }

    public function test_validaciones_del_nombre(): void
    {
        $this->permisoEnRolCliente(true);

        $this->renombrar(['nombre' => ''])->assertSessionHasErrors(['nombre' => 'Nombre es obligatorio.']);
        $this->renombrar(['nombre' => '    '])->assertSessionHasErrors('nombre');
        $this->renombrar([])->assertSessionHasErrors('nombre');
        $this->renombrar(['nombre' => str_repeat('a', 111)])->assertSessionHasErrors('nombre');
        $this->renombrar(['nombre' => ['arreglo']])->assertSessionHasErrors('nombre');
        $this->assertSame('Tarjeta 1', $this->producto->fresh()?->nombre);

        $this->renombrar(['nombre' => str_repeat('a', 110)])->assertSessionHasNoErrors();
        $this->assertSame(110, mb_strlen((string) $this->producto->fresh()?->nombre));

        // Sin unicidad: dos productos pueden llamarse igual.
        Producto::factory()->for($this->negocio)->create(['nombre' => 'Caja']);
        $this->renombrar(['nombre' => 'Caja'])->assertSessionHasNoErrors();
        $this->assertSame('Caja', $this->producto->fresh()?->nombre);
    }

    public function test_los_caracteres_especiales_se_guardan_tal_cual_y_se_muestran_escapados(): void
    {
        $this->permisoEnRolCliente(true);
        $nombre = '<script>alert(1)</script> Café & "Barra"';

        $this->renombrar(['nombre' => $nombre])->assertSessionHasNoErrors();
        $this->assertSame($nombre, $this->producto->fresh()?->nombre);

        // Viaja como dato JSON (Vue lo interpola como texto); nunca como HTML.
        $html = (string) $this->actingAs($this->cliente)->get(route('cliente.productos.show', $this->producto))->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_renombrar_no_cambia_codigo_enlace_ni_destinos(): void
    {
        $this->permisoEnRolCliente(true);
        $this->actingAs($this->admin)->post(route('admin.productos.store'), [
            'negocio_id' => $this->negocio->id,
            'nombre' => 'Tarjeta 2 QR',
            'tipo' => 'qr',
            'cantidad_qr' => 2,
            'url_destino' => 'https://www.instagram.com/negocio',
            'url_destino_qr2' => 'https://www.tiktok.com/@negocio',
            'estado_preparacion' => 'pendiente',
            'cantidad' => 1,
        ])->assertSessionHasNoErrors();
        $producto = Producto::query()->principales()->where('nombre', 'Tarjeta 2 QR')->firstOrFail();
        $qr2 = $producto->enlacesSecundarios()->sole();
        $antes = [$producto->codigo, $producto->url_permanente, $producto->url_destino, $qr2->codigo, $qr2->url_permanente, $qr2->url_destino];

        $this->actingAs($this->cliente)->patch(route('cliente.productos.nombre', $producto), ['nombre' => 'Recepción'])
            ->assertSessionHasNoErrors();

        $producto->refresh();
        $qr2->refresh();
        $this->assertSame('Recepción', $producto->nombre);
        $this->assertSame('Recepción', $qr2->nombre, 'El QR 2 comparte el nombre del producto físico.');
        $this->assertSame($antes, [$producto->codigo, $producto->url_permanente, $producto->url_destino, $qr2->codigo, $qr2->url_permanente, $qr2->url_destino]);

        // Las redirecciones siguen llevando a sus destinos.
        $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Safari/604.1';
        $this->withHeader('User-Agent', $ua)->get('/r/'.$producto->codigo)->assertRedirect('https://www.instagram.com/negocio');
        $this->withHeader('User-Agent', $ua)->get('/r/'.$qr2->codigo)->assertRedirect('https://www.tiktok.com/@negocio');

        // El QR 2 no se renombra por separado.
        $this->actingAs($this->cliente)->patch(route('cliente.productos.nombre', $qr2), ['nombre' => 'Solo QR 2'])->assertForbidden();
    }

    public function test_un_producto_suspendido_no_se_renombra(): void
    {
        $this->permisoEnRolCliente(true);
        $this->producto->forceFill(['estado' => 'suspendido', 'suspendido_en' => now()])->save();

        $this->renombrar(['nombre' => 'Nuevo'])->assertForbidden();
        $this->assertSame('Tarjeta 1', $this->producto->fresh()?->nombre);
    }

    public function test_el_permiso_no_afecta_destino_ni_diseno_y_los_administradores_conservan_su_edicion(): void
    {
        // Sin el permiso nuevo, el cliente sigue cambiando el destino.
        $this->actingAs($this->cliente)->put(route('cliente.productos.destino', $this->producto), ['url_destino' => 'https://www.facebook.com/negocio'])
            ->assertSessionHasNoErrors();
        $this->assertSame('https://www.facebook.com/negocio', $this->producto->fresh()?->url_destino);

        // El administrador edita como siempre (nombre y demás datos) y no usa la ruta del cliente.
        $this->actingAs($this->admin)->put(route('admin.productos.update', $this->producto), [
            'nombre' => 'Nombre de DASTI', 'tipo' => 'qr', 'estado_preparacion' => 'listo', 'referencia_comercial' => 'REF-002',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Nombre de DASTI', $this->producto->fresh()?->nombre);
        $this->assertSame('REF-002', $this->producto->fresh()?->referencia_comercial);
        $this->renombrar(['nombre' => 'X'], $this->admin)->assertForbidden();
    }

    public function test_al_retirar_el_permiso_ya_no_puede_renombrar(): void
    {
        $this->permisoEnRolCliente(true);
        $this->renombrar(['nombre' => 'Primero'])->assertSessionHasNoErrors();

        $this->permisoEnRolCliente(false);
        $this->renombrar(['nombre' => 'Segundo'])->assertForbidden();
        $this->assertSame('Primero', $this->producto->fresh()?->nombre);
    }
}
