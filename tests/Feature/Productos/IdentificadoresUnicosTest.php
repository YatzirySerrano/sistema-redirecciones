<?php

namespace Tests\Feature\Productos;

use App\Actions\Productos\CrearProducto;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\User;
use App\Services\Redirecciones\GeneradorCodigo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\LeeCodigosQr;
use Tests\TestCase;

/**
 * Regla comercial fundamental: cada unidad física (tarjeta NFC o QR) tiene su
 * propio código y su propia URL permanente, que no cambia con el destino.
 */
class IdentificadoresUnicosTest extends TestCase
{
    use LeeCodigosQr, RefreshDatabase;

    private User $admin;

    private Negocio $negocio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->negocio = Negocio::factory()->create(['nombre_comercial' => 'Cafetería Uno']);
    }

    /**
     * Alta real por lote desde el formulario del administrador.
     *
     * @return list<Producto>
     */
    private function registrarLote(string $tipo, int $cantidad, string $destino = 'https://www.example.org/menu', ?Negocio $negocio = null): array
    {
        $this->actingAs($this->admin)->post(route('admin.productos.store'), [
            'negocio_id' => ($negocio ?? $this->negocio)->id,
            'nombre' => 'Tarjeta',
            'tipo' => $tipo,
            'cantidad' => $cantidad,
            'url_destino' => $destino,
            'estado_preparacion' => 'pendiente',
        ])->assertSessionHasNoErrors();

        return Producto::query()->where('negocio_id', ($negocio ?? $this->negocio)->id)->where('tipo', $tipo)->orderBy('id')->get()->all();
    }

    public function test_cinco_tarjetas_nfc_reciben_cinco_codigos_y_urls_distintos(): void
    {
        $productos = $this->registrarLote('nfc', 5);

        $this->assertCount(5, $productos);
        $codigos = array_map(fn (Producto $p) => $p->codigo, $productos);
        $urls = array_map(fn (Producto $p) => $p->url_permanente, $productos);

        $this->assertCount(5, array_unique($codigos), 'Dos tarjetas comparten código.');
        $this->assertCount(5, array_unique($urls), 'Dos tarjetas comparten URL permanente.');

        foreach ($productos as $i => $producto) {
            $this->assertMatchesRegularExpression('/^[23456789abcdefghjkmnpqrstuvwxyz]{10}$/', $producto->codigo);
            $this->assertSame('https://redireccion.test/r/'.$producto->codigo, $producto->url_permanente);
            $this->assertSame('Tarjeta '.($i + 1), $producto->nombre);
        }
    }

    public function test_cinco_qr_reciben_codigos_distintos_y_cada_png_codifica_su_propia_url(): void
    {
        $productos = $this->registrarLote('qr', 5);
        $leidas = [];

        foreach ($productos as $producto) {
            $png = $this->actingAs($this->admin)
                ->get(route('productos.qr.show', [$producto, 'formato' => 'png', 'tamano' => 512, 'descargar' => 1]))
                ->assertOk()
                ->assertHeader('Content-Type', 'image/png')
                ->getContent();

            $leidas[] = $this->leerQr((string) $png);
            $this->assertSame($producto->url_permanente, end($leidas), "El QR de «{$producto->nombre}» no codifica su URL permanente.");
        }

        $this->assertCount(5, array_unique($leidas), 'Dos QR codifican la misma URL.');
    }

    public function test_el_mismo_destino_no_hace_compartir_el_codigo_y_cada_enlace_redirige_por_separado(): void
    {
        $productos = $this->registrarLote('nfc', 5, 'https://www.example.org/mismo-destino');

        $this->assertSame(['https://www.example.org/mismo-destino'], array_values(array_unique(array_map(fn (Producto $p) => $p->url_destino, $productos))));
        $this->assertCount(5, array_unique(array_map(fn (Producto $p) => $p->codigo, $productos)));

        foreach ($productos as $producto) {
            $this->get('/r/'.$producto->codigo)->assertRedirect('https://www.example.org/mismo-destino');
        }
    }

    public function test_cambiar_el_destino_de_una_tarjeta_no_altera_las_demas_ni_su_codigo(): void
    {
        $productos = $this->registrarLote('nfc', 3, 'https://www.example.org/inicial');
        $cliente = User::factory()->cliente($this->negocio)->create();
        $codigos = array_map(fn (Producto $p) => $p->codigo, $productos);

        $this->actingAs($cliente)
            ->put(route('cliente.productos.destino', $productos[1]), ['url_destino' => 'https://wa.me/5215512345678'])
            ->assertSessionHasNoErrors();

        $despues = Producto::query()->whereKey(array_map(fn (Producto $p) => $p->id, $productos))->orderBy('id')->get();

        $this->assertSame($codigos, $despues->pluck('codigo')->all(), 'Un cambio de destino modificó algún código.');
        $this->assertSame(['https://www.example.org/inicial', 'https://wa.me/5215512345678', 'https://www.example.org/inicial'], $despues->pluck('url_destino')->all());

        $this->get('/r/'.$codigos[0])->assertRedirect('https://www.example.org/inicial');
        $this->get('/r/'.$codigos[1])->assertRedirect('https://wa.me/5215512345678');
        $this->get('/r/'.$codigos[2])->assertRedirect('https://www.example.org/inicial');
    }

    public function test_la_url_para_grabar_en_el_nfc_es_la_del_mismo_producto(): void
    {
        [$primera, $segunda] = $this->registrarLote('nfc', 2);

        $this->actingAs($this->admin)->get(route('admin.productos.show', $segunda))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina
                ->component('admin/productos/Show')
                ->where('producto.data.id', $segunda->id)
                ->where('producto.data.codigo', $segunda->codigo)
                ->where('producto.data.url_permanente', 'https://redireccion.test/r/'.$segunda->codigo));

        $this->assertNotSame($primera->url_permanente, $segunda->url_permanente);
    }

    public function test_los_lotes_de_distintos_negocios_tampoco_comparten_codigos(): void
    {
        $otro = Negocio::factory()->create();
        $a = $this->registrarLote('combinado', 4);
        $b = $this->registrarLote('combinado', 4, negocio: $otro);

        $codigos = array_map(fn (Producto $p) => $p->codigo, [...$a, ...$b]);
        $this->assertCount(8, array_unique($codigos));
    }

    public function test_el_cliente_no_puede_alterar_el_codigo_ni_ver_codigos_de_otro_negocio(): void
    {
        [$propio] = $this->registrarLote('nfc', 1);
        $ajeno = Producto::factory()->create();
        $cliente = User::factory()->cliente($this->negocio)->create();
        $codigoOriginal = $propio->codigo;

        $this->actingAs($cliente)->put(route('cliente.productos.destino', $propio), [
            'url_destino' => 'https://www.example.org/nuevo',
            'codigo' => 'aaaaaaaaaa',
            'negocio_id' => $ajeno->negocio_id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($codigoOriginal, $propio->fresh()->codigo);
        $this->assertSame($this->negocio->id, $propio->fresh()->negocio_id);

        $this->actingAs($cliente)->get(route('cliente.productos.show', $ajeno))->assertNotFound();
        $this->actingAs($cliente)->get(route('productos.qr.show', $ajeno))->assertNotFound();
        $this->actingAs($cliente)->get(route('cliente.productos.index'))
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina
                ->has('productos.data', 1)
                ->where('productos.data.0.codigo', $codigoOriginal));
    }

    public function test_la_base_de_datos_rechaza_codigos_repetidos(): void
    {
        $existente = Producto::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Producto::factory()->create(['codigo' => $existente->codigo]);
    }

    public function test_si_dos_altas_simultaneas_generan_el_mismo_codigo_se_reintenta_con_otro(): void
    {
        $existente = Producto::factory()->create();

        // Simula la carrera: la comprobación previa del generador no ve el
        // código (otra solicitud lo insertó entre la comprobación y el INSERT).
        $generador = new class($existente->codigo) extends GeneradorCodigo
        {
            private int $llamadas = 0;

            public function __construct(private readonly string $repetido) {}

            public function generar(): string
            {
                return $this->llamadas++ === 0 ? $this->repetido : 'nuevocodig';
            }
        };
        $this->app->instance(GeneradorCodigo::class, $generador);

        $productos = app(CrearProducto::class)->ejecutar($this->negocio, ['nombre' => 'Tarjeta', 'tipo' => 'nfc'], $this->admin);

        $this->assertSame('nuevocodig', $productos[0]->codigo);
        $this->assertSame(1, Producto::query()->where('codigo', $existente->codigo)->count());
        $this->assertSame(2, Producto::query()->count());
    }
}
