<?php

namespace Tests\Feature\Productos;

use App\Models\CambioDestino;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\RegistroAuditoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Transferencia de un producto entre negocios: el nuevo propietario toma el
 * control sin ver destinos, actividad ni estadísticas del anterior, y el
 * administrador conserva la trazabilidad completa.
 */
class TransferenciaAisladaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Negocio $origen;

    private Negocio $destino;

    private User $clienteOrigen;

    private User $clienteDestino;

    private Producto $producto;

    private const URL_PRIVADA = 'https://www.example.org/promocion-privada-del-origen';

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->origen = Negocio::factory()->create(['nombre_comercial' => 'Negocio Origen']);
        $this->destino = Negocio::factory()->create(['nombre_comercial' => 'Negocio Destino']);
        $this->clienteOrigen = User::factory()->cliente($this->origen)->create();
        $this->clienteDestino = User::factory()->cliente($this->destino)->create();

        $this->producto = Producto::factory()->for($this->origen)->create(['url_destino' => 'https://www.example.org/inicial']);

        // Historial y actividad del periodo del propietario anterior.
        $this->actingAs($this->clienteOrigen)
            ->put(route('cliente.productos.destino', $this->producto), ['url_destino' => self::URL_PRIVADA])
            ->assertSessionHasNoErrors();

        for ($i = 0; $i < 3; $i++) {
            $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Safari/604.1')
                ->get('/r/'.$this->producto->codigo)->assertRedirect(self::URL_PRIVADA);
        }
    }

    private function transferir(bool $conservarDestino = false): void
    {
        $this->actingAs($this->admin)->post(route('admin.productos.transferir', $this->producto), [
            'negocio_id' => $this->destino->id,
            'motivo' => 'Reasignación por cambio de dueño',
            'conservar_destino' => $conservarDestino,
        ])->assertSessionHasNoErrors();
    }

    public function test_antes_de_transferir_el_propietario_ve_su_historial_y_actividad(): void
    {
        $this->actingAs($this->clienteOrigen)->get(route('cliente.productos.show', $this->producto))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina
                ->where('producto.data.total_accesos', 3)
                ->whereNot('producto.data.ultimo_acceso_en', null)
                ->where('estadisticas.totales.total', 3)
                ->has('cambios.data', 1)
                ->where('cambios.data.0.url_nueva', self::URL_PRIVADA));

        $this->actingAs($this->clienteDestino)->get(route('cliente.productos.show', $this->producto))->assertNotFound();
    }

    public function test_el_nuevo_propietario_no_ve_destinos_actividad_ni_estadisticas_del_anterior(): void
    {
        $codigo = $this->producto->codigo;
        $this->transferir();

        $this->actingAs($this->clienteDestino)->get(route('cliente.productos.show', $this->producto))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $pagina) use ($codigo) {
                $pagina
                    ->where('producto.data.codigo', $codigo)
                    ->where('producto.data.url_permanente', 'https://redireccion.test/r/'.$codigo)
                    ->where('producto.data.url_destino', null)
                    ->where('producto.data.total_accesos', 0)
                    ->where('producto.data.ultimo_acceso_en', null)
                    ->where('estadisticas.totales.total', 0)
                    ->has('cambios.data', 0);

                $json = json_encode($pagina->toArray()['props']);
                $this->assertStringNotContainsString('promocion-privada-del-origen', (string) $json);
                $this->assertStringNotContainsString('Negocio Origen', (string) $json);
            });

        $this->actingAs($this->clienteDestino)->get(route('cliente.inicio'))
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina
                ->where('indicadores.accesos_totales', 0)
                ->where('indicadores.accesos_30_dias', 0)
                ->has('actividad.data', 0));

        $this->actingAs($this->clienteDestino)->get(route('cliente.estadisticas.index', ['producto_id' => $this->producto->id]))
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('totales.total', 0));
    }

    public function test_conservando_el_destino_el_nuevo_propietario_solo_ve_su_punto_de_partida(): void
    {
        $this->transferir(conservarDestino: true);

        $this->actingAs($this->clienteDestino)->get(route('cliente.productos.show', $this->producto))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina
                ->where('producto.data.url_destino', self::URL_PRIVADA)
                ->has('cambios.data', 1)
                ->where('cambios.data.0.url_anterior', null)
                ->where('cambios.data.0.url_nueva', self::URL_PRIVADA)
                ->where('cambios.data.0.autor', 'Sistema'));

        // La URL inicial del anterior propietario (antes de su cambio) nunca aparece.
        $this->actingAs($this->clienteDestino)->get(route('cliente.productos.show', $this->producto))
            ->assertDontSee('example.org/inicial', false);
    }

    public function test_el_propietario_anterior_pierde_el_acceso_y_conserva_solo_su_periodo(): void
    {
        $this->transferir();

        $this->actingAs($this->clienteOrigen)->get(route('cliente.productos.show', $this->producto))->assertNotFound();
        $this->actingAs($this->clienteOrigen)->put(route('cliente.productos.destino', $this->producto), ['url_destino' => 'https://www.example.org/x'])->assertNotFound();
        $this->actingAs($this->clienteOrigen)->get(route('productos.qr.show', $this->producto))->assertNotFound();

        // Sus indicadores ya no incluyen el producto transferido.
        $this->actingAs($this->clienteOrigen)->get(route('cliente.inicio'))
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina
                ->where('indicadores.productos', 0)
                ->where('indicadores.accesos_totales', 0)
                ->where('indicadores.accesos_30_dias', 0));

        // El registro del retiro del destino pertenece al periodo del origen.
        $retiro = CambioDestino::query()->whereNull('url_nueva')->sole();
        $this->assertSame($this->origen->id, $retiro->negocio_id);
        $this->assertSame(self::URL_PRIVADA, $retiro->url_anterior);
    }

    public function test_los_accesos_posteriores_cuentan_solo_para_el_nuevo_propietario(): void
    {
        $this->transferir();
        $this->actingAs($this->admin)->put(route('admin.productos.destino', $this->producto), ['url_destino' => 'https://www.example.org/destino-nuevo']);

        $this->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 15) Chrome/130.0 Mobile Safari/537.36')
            ->get('/r/'.$this->producto->codigo)->assertRedirect('https://www.example.org/destino-nuevo');

        $producto = $this->producto->fresh();
        $this->assertSame(1, $producto->total_accesos);
        $this->assertSame(3, (int) \DB::table('accesos_diarios')->where('negocio_id', $this->origen->id)->sum('total'));
        $this->assertSame(1, (int) \DB::table('accesos_diarios')->where('negocio_id', $this->destino->id)->sum('total'));

        $this->actingAs($this->clienteDestino)->get(route('cliente.productos.show', $this->producto))
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina
                ->where('producto.data.total_accesos', 1)
                ->where('estadisticas.totales.total', 1));
    }

    public function test_el_administrador_conserva_la_trazabilidad_completa(): void
    {
        $this->transferir();

        $this->actingAs($this->admin)->get(route('admin.productos.show', $this->producto))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina
                ->where('producto.data.negocio.id', $this->destino->id)
                ->has('cambios.data', 2));

        $transferido = RegistroAuditoria::query()->where('accion', 'producto.transferido')->sole();
        $this->assertSame($this->origen->id, $transferido->negocio_id);
        $this->assertSame(self::URL_PRIVADA, $transferido->valores_anteriores['url_destino']);
        $this->assertSame(3, $transferido->valores_anteriores['total_accesos']);

        $recibido = RegistroAuditoria::query()->where('accion', 'producto.recibido')->sole();
        $this->assertSame($this->destino->id, $recibido->negocio_id);
        $this->assertArrayNotHasKey('url_destino', $recibido->valores_anteriores ?? []);

        // Las estadísticas globales del administrador siguen sumando ambos periodos.
        $this->actingAs($this->admin)->get(route('admin.panel'))
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('indicadores.accesos_totales', 3));
    }
}
