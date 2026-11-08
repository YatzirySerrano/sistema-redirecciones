<?php

namespace Tests\Feature\Productos;

use App\Enums\EstadoProducto;
use App\Models\CambioDestino;
use App\Models\EventoAcceso;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\RegistroAuditoria;
use App\Models\Rol;
use App\Models\User;
use App\Services\Autorizacion\CatalogoPermisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Productos con uno o dos QR impresos, cada uno con su propio enlace,
 * destino, diseño y estadísticas.
 */
class DosQrTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $extra
     */
    private function alta(array $extra = []): Producto
    {
        $this->actingAs($this->admin)->post(route('admin.productos.store'), [
            'negocio_id' => $this->negocio->id,
            'nombre' => 'Tarjeta 2 QR',
            'tipo' => 'qr',
            'cantidad_qr' => 2,
            'url_destino' => 'https://www.instagram.com/negocio',
            'url_destino_qr2' => 'https://www.tiktok.com/@negocio',
            'estado_preparacion' => 'pendiente',
            'cantidad' => 1,
            ...$extra,
        ])->assertSessionHasNoErrors();

        return Producto::query()->principales()->where('negocio_id', $this->negocio->id)->latest('id')->firstOrFail();
    }

    private function permitirAClientes(): void
    {
        Rol::porClave(CatalogoPermisos::ROL_CLIENTE)?->givePermissionTo('productos.cambiar_cantidad_qr');
    }

    public function test_el_permiso_es_de_ambos_ambitos_y_solo_lo_tiene_dasti_de_inicio(): void
    {
        $this->assertTrue(CatalogoPermisos::admiteAmbito('productos.cambiar_cantidad_qr', 'negocio'));
        $this->assertTrue(CatalogoPermisos::admiteAmbito('productos.cambiar_cantidad_qr', 'plataforma'));
        $this->assertTrue(Rol::porClave(CatalogoPermisos::ROL_ADMINISTRADOR)?->hasPermissionTo('productos.cambiar_cantidad_qr'));
        $this->assertFalse(Rol::porClave(CatalogoPermisos::ROL_CLIENTE)?->hasPermissionTo('productos.cambiar_cantidad_qr'));
        $this->assertFalse(Rol::porClave(CatalogoPermisos::ROL_EMPLEADO)?->hasPermissionTo('productos.cambiar_cantidad_qr'));
    }

    public function test_alta_qr_con_dos_qr_crea_dos_enlaces_con_codigos_y_destinos_distintos(): void
    {
        $producto = $this->alta();
        $qr2 = $producto->enlacesSecundarios()->sole();

        $this->assertSame(2, $producto->cantidad_qr);
        $this->assertSame(1, $producto->numero_qr);
        $this->assertSame(2, $qr2->numero_qr);
        $this->assertSame('qr', $producto->medio?->value);
        $this->assertSame('qr', $qr2->medio?->value);
        $this->assertTrue($producto->tieneDestinosIndependientes());
        $this->assertNotSame($producto->codigo, $qr2->codigo);
        $this->assertSame('QR 1', $producto->etiquetaMedio());
        $this->assertSame('QR 2', $qr2->etiquetaMedio());

        // Cada QR redirige a su destino y acumula sus propios accesos.
        $this->withHeader('User-Agent', self::UA)->get('/r/'.$producto->codigo)->assertRedirect('https://www.instagram.com/negocio');
        $this->withHeader('User-Agent', self::UA)->get('/r/'.$qr2->codigo)->assertRedirect('https://www.tiktok.com/@negocio');
        $this->assertSame(1, EventoAcceso::query()->where('producto_id', $qr2->id)->count());

        // Una sola unidad vendida; el listado indica 2 QR.
        $this->assertSame(1, Negocio::query()->conResumen()->findOrFail($this->negocio->id)->productos_count);
        $this->actingAs($this->admin)->get(route('admin.productos.index'))
            ->assertInertia(fn (Assert $page) => $page->where('productos.meta.total', 1)
                ->where('productos.data.0.cantidad_qr', 2)
                ->where('productos.data.0.enlaces_secundarios.0.medio_etiqueta', 'QR 2'));

        $auditoria = RegistroAuditoria::query()->where('accion', 'producto.creado')->sole();
        $this->assertSame($qr2->codigo, $auditoria->valores_nuevos['codigo_qr2'] ?? null);
    }

    public function test_alta_combinado_con_dos_qr_crea_nfc_qr1_y_qr2_independientes(): void
    {
        $producto = $this->alta([
            'tipo' => 'combinado',
            'modalidad_destino' => 'compartido', // 2 QR implica independientes
            'url_destino' => 'https://wa.me/5215512345678',
            'url_destino_qr' => 'https://www.instagram.com/negocio',
        ]);
        [$qr1, $qr2] = $producto->enlacesSecundarios()->get()->all();

        $this->assertSame('independiente', $producto->modalidad_destino->value);
        $this->assertSame('nfc', $producto->medio?->value);
        $this->assertSame(['QR 1', 'QR 2'], [$qr1->etiquetaMedio(), $qr2->etiquetaMedio()]);
        $this->assertCount(3, array_unique([$producto->codigo, $qr1->codigo, $qr2->codigo]));
        $this->get('/r/'.$producto->codigo)->assertRedirect('https://wa.me/5215512345678');
        $this->get('/r/'.$qr1->codigo)->assertRedirect('https://www.instagram.com/negocio');
        $this->get('/r/'.$qr2->codigo)->assertRedirect('https://www.tiktok.com/@negocio');
    }

    public function test_alta_con_un_qr_y_nfc_no_cambian(): void
    {
        $qr = $this->alta(['cantidad_qr' => 1, 'nombre' => 'Un QR']);
        $this->assertSame(1, $qr->cantidad_qr);
        $this->assertSame('compartido', $qr->modalidad_destino->value);
        $this->assertNull($qr->medio);
        $this->assertSame(0, $qr->enlacesSecundarios()->count());

        // NFC: la pregunta no aplica aunque se envíe.
        $nfc = $this->alta(['tipo' => 'nfc', 'nombre' => 'NFC']);
        $this->assertNull($nfc->cantidad_qr);
        $this->assertSame(0, $nfc->cantidadQr());
        $this->assertSame(0, $nfc->enlacesSecundarios()->count());
    }

    public function test_el_cliente_personaliza_destino_y_diseno_de_cada_qr(): void
    {
        $producto = $this->alta();
        $qr2 = $producto->enlacesSecundarios()->sole();
        $cliente = User::factory()->cliente($this->negocio)->create();

        $this->actingAs($cliente)->get(route('cliente.productos.show', $producto))
            ->assertInertia(fn (Assert $page) => $page
                ->where('producto.data.cantidad_qr', 2)
                ->where('producto.data.medio_etiqueta', 'QR 1')
                ->where('producto.data.enlaces_secundarios.0.medio_etiqueta', 'QR 2')
                ->where('producto.data.permisos.cambiar_cantidad_qr', false)
                ->has('opciones_qr_enlaces.'.$producto->id)
                ->has('opciones_qr_enlaces.'.$qr2->id));

        $this->actingAs($cliente)->put(route('cliente.productos.destino', $qr2), ['url_destino' => 'https://example.org/qr2'])
            ->assertSessionHasNoErrors();
        $this->assertSame('https://example.org/qr2', $qr2->fresh()?->url_destino);
        $this->assertSame('https://www.instagram.com/negocio', $producto->fresh()?->url_destino);

        $this->actingAs($cliente)->put(route('productos.qr.update', $qr2), [
            'color' => '#1E3A8A', 'fondo' => '#FFFFFF', 'fondo_transparente' => false, 'margen' => 'estandar', 'incluir_logo' => false,
        ])->assertSessionHasNoErrors();
        $this->assertSame('#1E3A8A', $qr2->fresh()?->opciones_qr['color'] ?? null);
        $this->assertNull($producto->fresh()?->opciones_qr);
    }

    public function test_aislamiento_multiempresa(): void
    {
        $producto = $this->alta();
        $qr2 = $producto->enlacesSecundarios()->sole();
        $this->permitirAClientes();
        $ajeno = User::factory()->cliente()->create();

        $this->actingAs($ajeno)->get(route('cliente.productos.show', $qr2))->assertNotFound();
        $this->actingAs($ajeno)->put(route('cliente.productos.destino', $qr2), ['url_destino' => 'https://example.org'])->assertNotFound();
        $this->actingAs($ajeno)->put(route('productos.qr.update', $qr2), ['color' => '#000000'])->assertNotFound();
        $this->actingAs($ajeno)->patch(route('cliente.productos.cantidad-qr', $producto), ['cantidad_qr' => 1])->assertNotFound();
        $this->assertSame(2, $producto->fresh()?->cantidad_qr);
    }

    public function test_transferir_mueve_ambos_qr(): void
    {
        $producto = $this->alta();
        $qr2 = $producto->enlacesSecundarios()->sole();
        $nuevo = Negocio::factory()->create();
        $anterior = User::factory()->cliente($this->negocio)->create();

        $this->actingAs($this->admin)->post(route('admin.productos.transferir', $producto), [
            'negocio_id' => $nuevo->id, 'motivo' => 'Cambio de dueño', 'conservar_destino' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame($nuevo->id, $producto->fresh()?->negocio_id);
        $this->assertSame($nuevo->id, $qr2->fresh()?->negocio_id);
        $this->assertSame('https://www.tiktok.com/@negocio', $qr2->fresh()?->url_destino);
        $this->actingAs($anterior)->get(route('cliente.productos.show', $qr2))->assertNotFound();
    }

    public function test_suspender_el_producto_suspende_ambos_qr(): void
    {
        $producto = $this->alta();
        $qr2 = $producto->enlacesSecundarios()->sole();

        $this->actingAs($this->admin)->patch(route('admin.productos.estado', $producto), ['estado' => 'suspendido'])->assertSessionHasNoErrors();
        $this->get('/r/'.$qr2->codigo)->assertStatus(410);
        $this->actingAs($this->admin)->patch(route('admin.productos.estado', $producto), ['estado' => 'activo'])->assertSessionHasNoErrors();
        $this->get('/r/'.$qr2->codigo)->assertRedirect('https://www.tiktok.com/@negocio');
    }

    public function test_admin_amplia_de_uno_a_dos_qr_conservando_el_codigo_del_qr_impreso(): void
    {
        $producto = $this->alta(['cantidad_qr' => 1, 'url_destino_qr2' => null]);
        $codigo = $producto->codigo;

        $this->actingAs($this->admin)->patch(route('admin.productos.cantidad-qr', $producto), [
            'cantidad_qr' => 2, 'motivo' => 'Reimpresión con dos QR', 'url_destino_qr2' => 'https://example.org/dos',
        ])->assertSessionHasNoErrors();

        $producto->refresh();
        $qr2 = $producto->enlacesSecundarios()->sole();
        $this->assertSame($codigo, $producto->codigo);
        $this->assertSame(2, $producto->cantidad_qr);
        $this->assertSame('QR 1', $producto->etiquetaMedio());
        $this->assertSame('QR 2', $qr2->etiquetaMedio());
        $this->get('/r/'.$codigo)->assertRedirect('https://www.instagram.com/negocio');
        $this->get('/r/'.$qr2->codigo)->assertRedirect('https://example.org/dos');

        $registro = RegistroAuditoria::query()->where('accion', 'producto.qr_ampliado')->sole();
        $this->assertSame('Reimpresión con dos QR', $registro->valores_nuevos['motivo'] ?? null);
    }

    public function test_ampliar_un_combinado_compartido_deja_el_enlace_actual_para_el_nfc(): void
    {
        $producto = $this->alta(['tipo' => 'combinado', 'cantidad_qr' => 1, 'url_destino_qr2' => null]);
        $this->assertSame('compartido', $producto->modalidad_destino->value);

        $this->actingAs($this->admin)->patch(route('admin.productos.cantidad-qr', $producto), ['cantidad_qr' => 2])->assertSessionHasNoErrors();

        $producto->refresh();
        [$qr1, $qr2] = $producto->enlacesSecundarios()->get()->all();
        $this->assertSame('nfc', $producto->medio?->value);
        $this->assertSame('independiente', $producto->modalidad_destino->value);
        // QR 1 parte con el mismo destino; QR 2 queda por configurar.
        $this->assertSame($producto->url_destino, $qr1->url_destino);
        $this->assertNull($qr2->url_destino);
        $this->assertSame(['QR 1', 'QR 2'], [$qr1->etiquetaMedio(), $qr2->etiquetaMedio()]);
    }

    public function test_reducir_archiva_el_qr2_sin_borrar_su_historial_y_ampliar_lo_reactiva(): void
    {
        $producto = $this->alta();
        $qr2 = $producto->enlacesSecundarios()->sole();
        $this->withHeader('User-Agent', self::UA)->get('/r/'.$qr2->codigo)->assertRedirect();

        $this->actingAs($this->admin)->patch(route('admin.productos.cantidad-qr', $producto), ['cantidad_qr' => 1])->assertSessionHasNoErrors();

        $producto->refresh();
        $archivado = $qr2->fresh();
        $this->assertSame(1, $producto->cantidad_qr);
        $this->assertNull($producto->medio);
        $this->assertSame('compartido', $producto->modalidad_destino->value);
        $this->assertNotNull($archivado?->archivado_en);
        $this->assertSame(EstadoProducto::Suspendido, $archivado->estado);
        $this->assertSame(0, $producto->enlacesSecundarios()->count());
        $this->get('/r/'.$qr2->codigo)->assertStatus(410);
        // Historial y estadísticas del QR 2 se conservan (el acceso redirigido
        // previo y el intento posterior, ya no disponible).
        $this->assertSame(1, EventoAcceso::query()->where('producto_id', $qr2->id)->where('resultado', 'redirigido')->count());
        $this->assertSame(2, EventoAcceso::query()->where('producto_id', $qr2->id)->count());
        $this->assertSame(1, CambioDestino::query()->where('producto_id', $qr2->id)->count());

        // Reactivar el producto no reactiva el QR archivado.
        $this->actingAs($this->admin)->patch(route('admin.productos.estado', $producto), ['estado' => 'suspendido']);
        $this->actingAs($this->admin)->patch(route('admin.productos.estado', $producto), ['estado' => 'activo']);
        $this->assertSame(EstadoProducto::Suspendido, $qr2->fresh()?->estado);

        // Volver a 2 QR reactiva el mismo enlace (mismo código y destino).
        $this->actingAs($this->admin)->patch(route('admin.productos.cantidad-qr', $producto), ['cantidad_qr' => 2])->assertSessionHasNoErrors();
        $this->assertSame($qr2->id, $producto->enlacesSecundarios()->sole()->id);
        $this->get('/r/'.$qr2->codigo)->assertRedirect('https://www.tiktok.com/@negocio');
        $this->assertSame(1, Producto::query()->where('producto_principal_id', $producto->id)->count());
    }

    public function test_cliente_sin_permiso_recibe_403(): void
    {
        $producto = $this->alta();
        $cliente = User::factory()->cliente($this->negocio)->create();

        $this->actingAs($cliente)->patch(route('cliente.productos.cantidad-qr', $producto), ['cantidad_qr' => 1])->assertForbidden();
        $this->assertSame(2, $producto->fresh()?->cantidad_qr);
    }

    public function test_cliente_con_permiso_amplia_y_reduce(): void
    {
        $this->permitirAClientes();
        $producto = $this->alta(['cantidad_qr' => 1, 'url_destino_qr2' => null]);
        $cliente = User::factory()->cliente($this->negocio)->create();

        $this->actingAs($cliente)->get(route('cliente.productos.show', $producto))
            ->assertInertia(fn (Assert $page) => $page->where('producto.data.permisos.cambiar_cantidad_qr', true));

        $this->actingAs($cliente)->patch(route('cliente.productos.cantidad-qr', $producto), ['cantidad_qr' => 2])->assertSessionHasNoErrors();
        $this->assertSame(2, $producto->fresh()?->cantidad_qr);
        $qr2 = $producto->enlacesSecundarios()->sole();
        $this->assertSame($cliente->id, RegistroAuditoria::query()->where('accion', 'producto.qr_ampliado')->sole()->user_id);

        $this->actingAs($cliente)->patch(route('cliente.productos.cantidad-qr', $producto), ['cantidad_qr' => 1, 'motivo' => 'Ya no lo uso'])->assertSessionHasNoErrors();
        $this->assertSame(1, $producto->fresh()?->cantidad_qr);
        $this->assertNotNull($qr2->fresh()?->archivado_en);
    }

    public function test_no_aplica_a_nfc_ni_a_productos_suspendidos(): void
    {
        $this->permitirAClientes();
        $cliente = User::factory()->cliente($this->negocio)->create();
        $nfc = $this->alta(['tipo' => 'nfc', 'nombre' => 'NFC']);
        $suspendido = $this->alta(['cantidad_qr' => 1, 'nombre' => 'Suspendido']);
        $this->actingAs($this->admin)->patch(route('admin.productos.estado', $suspendido), ['estado' => 'suspendido']);

        $this->actingAs($cliente)->patch(route('cliente.productos.cantidad-qr', $nfc), ['cantidad_qr' => 2])->assertForbidden();
        $this->actingAs($cliente)->patch(route('cliente.productos.cantidad-qr', $suspendido), ['cantidad_qr' => 2])->assertForbidden();
        $this->actingAs($this->admin)->patch(route('admin.productos.cantidad-qr', $nfc), ['cantidad_qr' => 2])->assertForbidden();
    }

    public function test_un_producto_con_dos_qr_no_cambia_de_tipo_y_se_elimina_completo(): void
    {
        $producto = $this->alta();

        $this->actingAs($this->admin)->put(route('admin.productos.update', $producto), [
            'nombre' => 'Otro', 'tipo' => 'nfc', 'estado_preparacion' => 'pendiente',
        ])->assertSessionHasErrors('tipo');

        $this->actingAs($this->admin)->delete(route('admin.productos.destroy', $producto))->assertSessionHasNoErrors();
        $this->assertSame(0, Producto::query()->where('negocio_id', $this->negocio->id)->count());
    }

    public function test_estado_de_fabrica_dos_qr(): void
    {
        $producto = Producto::factory()->for($this->negocio)->dosQr()->create();

        $this->assertSame(2, $producto->cantidadQr());
        $this->assertSame([1, 2], array_map(fn (Producto $p) => $p->numero_qr, $producto->load('enlacesSecundarios')->enlacesQr()));
    }
}
