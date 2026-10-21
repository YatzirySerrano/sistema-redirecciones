<?php

namespace Tests\Feature\Admin;

use App\Enums\EstadoCuenta;
use App\Enums\RolUsuario;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\RegistroAuditoria;
use App\Models\User;
use App\Notifications\InvitacionAcceso;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class NegociosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->administrador()->create();
    }

    public function test_el_administrador_crea_un_negocio_con_acceso_temporal(): void
    {
        $this->actingAs($this->admin)->post(route('admin.negocios.store'), [
            'nombre_comercial' => 'Cafetería Aurora',
            'responsable' => 'Ana Ruiz',
            'email_contacto' => 'Contacto@Aurora.test',
            'telefono' => '55 1111 2222',
            'estado' => 'activo',
            'crear_acceso' => true,
            'acceso_nombre' => 'Ana Ruiz',
            'acceso_email' => 'ana@aurora.test',
            'acceso_metodo' => 'temporal',
            'acceso_password' => 'Temporal-2026',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $negocio = Negocio::query()->sole();
        $this->assertSame('contacto@aurora.test', $negocio->email_contacto);

        $cliente = User::query()->where('email', 'ana@aurora.test')->sole();
        $this->assertSame(RolUsuario::Cliente, $cliente->rol);
        $this->assertSame($negocio->id, $cliente->negocio_id);
        $this->assertTrue($cliente->debe_cambiar_password);
        $this->assertTrue(Hash::check('Temporal-2026', $cliente->password));
        $this->assertNotSame('Temporal-2026', $cliente->getRawOriginal('password'));

        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'negocio.creado', 'negocio_id' => $negocio->id, 'user_id' => $this->admin->id]);
        $alta = RegistroAuditoria::query()->where('accion', 'usuario.creado')->sole();
        $this->assertStringNotContainsString('Temporal-2026', json_encode($alta->getAttributes()));
    }

    public function test_el_acceso_por_invitacion_envia_correo_sin_exponer_contrasena(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)->post(route('admin.negocios.store'), [
            'nombre_comercial' => 'Panadería Sol',
            'estado' => 'activo',
            'crear_acceso' => true,
            'acceso_nombre' => 'Luis',
            'acceso_email' => 'luis@sol.test',
            'acceso_metodo' => 'invitacion',
        ])->assertSessionHasNoErrors();

        $usuario = User::query()->where('email', 'luis@sol.test')->sole();
        Notification::assertSentTo($usuario, InvitacionAcceso::class);
    }

    public function test_valida_los_datos_del_negocio(): void
    {
        $this->actingAs($this->admin)->post(route('admin.negocios.store'), [
            'nombre_comercial' => '',
            'email_contacto' => 'no-es-correo',
            'telefono' => '<script>',
            'estado' => 'otro',
            'crear_acceso' => true,
            'acceso_email' => $this->admin->email,
            'acceso_metodo' => 'temporal',
        ])->assertSessionHasErrors(['nombre_comercial', 'email_contacto', 'telefono', 'estado', 'acceso_nombre', 'acceso_email', 'acceso_password']);

        $this->assertSame(0, Negocio::query()->count());
    }

    public function test_listado_con_busqueda_filtros_y_contadores(): void
    {
        $negocio = Negocio::factory()->create(['nombre_comercial' => 'Florería Azul']);
        Producto::factory()->count(2)->for($negocio)->create();
        Producto::factory()->qr()->suspendido()->for($negocio)->create();
        Negocio::factory()->create(['nombre_comercial' => 'Taller Rojo']);

        $this->actingAs($this->admin)
            ->get(route('admin.negocios.index', ['buscar' => 'florer']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('admin/negocios/Index')
                ->has('negocios.data', 1)
                ->where('negocios.data.0.nombre_comercial', 'Florería Azul')
                ->where('negocios.data.0.productos_count', 3)
                ->where('negocios.data.0.productos_nfc_count', 2)
                ->where('negocios.data.0.productos_qr_count', 1)
                ->where('negocios.data.0.productos_activos_count', 2));
    }

    public function test_la_actualizacion_queda_auditada_con_valores_anteriores(): void
    {
        $negocio = Negocio::factory()->create(['telefono' => '55 0000 0000']);

        $this->actingAs($this->admin)->put(route('admin.negocios.update', $negocio), [
            'nombre_comercial' => $negocio->nombre_comercial,
            'telefono' => '55 9999 9999',
        ])->assertSessionHasNoErrors();

        $registro = RegistroAuditoria::query()->where('accion', 'negocio.actualizado')->sole();
        $this->assertSame('55 0000 0000', $registro->valores_anteriores['telefono']);
        $this->assertSame('55 9999 9999', $registro->valores_nuevos['telefono']);
    }

    public function test_suspender_y_reactivar_negocio(): void
    {
        $negocio = Negocio::factory()->create();

        $this->actingAs($this->admin)->patch(route('admin.negocios.estado', $negocio), ['estado' => 'suspendido', 'motivo' => 'Falta de pago'])
            ->assertSessionHasNoErrors();
        $this->assertSame(EstadoCuenta::Suspendido, $negocio->fresh()->estado);
        $this->assertNotNull($negocio->fresh()->suspendido_en);

        $this->actingAs($this->admin)->patch(route('admin.negocios.estado', $negocio), ['estado' => 'activo']);
        $this->assertSame(EstadoCuenta::Activo, $negocio->fresh()->estado);

        $this->assertSame(['negocio.suspendido', 'negocio.reactivado'], RegistroAuditoria::query()->orderBy('id')->pluck('accion')->all());
    }

    public function test_ficha_del_negocio(): void
    {
        $negocio = Negocio::factory()->create();
        User::factory()->cliente($negocio)->create();
        Producto::factory()->for($negocio)->create();

        $this->actingAs($this->admin)->get(route('admin.negocios.show', $negocio))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('admin/negocios/Show')
                ->has('usuarios.data', 1)
                ->has('productos.data', 1)
                ->has('estadisticas.serie', 30));
    }

    public function test_logotipo_se_recodifica_y_se_guarda_en_disco_privado(): void
    {
        Storage::fake('local');
        $negocio = Negocio::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.negocios.logo.store', $negocio), [
            'logo' => UploadedFile::fake()->image('logo.jpg', 300, 300),
        ])->assertSessionHasNoErrors();

        $ruta = $negocio->fresh()->logo_path;
        $this->assertNotNull($ruta);
        $this->assertStringEndsWith('.png', $ruta);
        Storage::disk('local')->assertExists($ruta);

        $this->actingAs($this->admin)->get(route('admin.negocios.logo.show', $negocio))->assertOk();

        $this->actingAs($this->admin)->post(route('admin.negocios.logo.store', $negocio), [
            'logo' => UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml'),
        ])->assertSessionHasErrors('logo');

        $this->actingAs($this->admin)->delete(route('admin.negocios.logo.destroy', $negocio));
        Storage::disk('local')->assertMissing($ruta);
        $this->assertNull($negocio->fresh()->logo_path);
    }

    public function test_integridad_no_se_puede_borrar_un_negocio_con_productos(): void
    {
        $negocio = Negocio::factory()->create();
        Producto::factory()->for($negocio)->create();

        $this->expectException(QueryException::class);
        $negocio->delete();
    }
}
