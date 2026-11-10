<?php

namespace Tests\Feature\Estadisticas;

use App\Enums\ResultadoAcceso;
use App\Jobs\RegistrarEventoAcceso;
use App\Models\EventoAcceso;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\User;
use App\Services\Estadisticas\ClasificadorAgente;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Coherencia de las cifras de la ficha de un producto (administración y
 * cliente): el resumen de 30 días, la gráfica y el total histórico son del
 * producto completo, es decir, de todos sus enlaces (QR 1 y QR 2, o frente
 * NFC y reverso QR); el detalle por enlace suma exactamente lo mismo.
 */
class FichaProductoEstadisticasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Negocio $negocio;

    private User $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['dasti.cache_estadisticas_segundos' => 0]);
        $this->travelTo(CarbonImmutable::parse('2026-11-12 13:00')); // jueves

        $this->admin = User::factory()->create();
        $this->negocio = Negocio::factory()->create();
        $this->cliente = User::factory()->cliente($this->negocio)->create();
    }

    private function acceso(Producto $enlace, string $momento, bool $bot = false, string $huella = 'h'): void
    {
        (new RegistrarEventoAcceso(
            productoId: $enlace->id,
            negocioId: $enlace->negocio_id,
            resultado: ResultadoAcceso::Redirigido,
            ocurridoEn: CarbonImmutable::parse($momento)->toDateTimeString(),
            agente: $bot ? 'curl/8.0' : 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Safari/604.1',
            huellaDiaria: $huella,
        ))->handle(app(ClasificadorAgente::class));
    }

    private function dosQr(): Producto
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
        ])->assertSessionHasNoErrors();

        return Producto::query()->principales()->where('negocio_id', $this->negocio->id)->latest('id')->firstOrFail();
    }

    /**
     * Mismas comprobaciones en la ficha de administración y en la del cliente.
     *
     * @param  callable(Assert): mixed  $comprobar
     */
    private function enAmbasFichas(Producto $producto, callable $comprobar): void
    {
        $this->actingAs($this->admin)->get(route('admin.productos.show', $producto))
            ->assertOk()->assertInertia(fn (Assert $p) => $comprobar($p));
        $this->actingAs($this->cliente)->get(route('cliente.productos.show', $producto))
            ->assertOk()->assertInertia(fn (Assert $p) => $comprobar($p));
    }

    /**
     * @param  list<array{fecha: string, total: int}>  $serie
     */
    private function sumaSerie(array $serie): int
    {
        return array_sum(array_column($serie, 'total'));
    }

    public function test_producto_sin_accesos(): void
    {
        $producto = Producto::factory()->for($this->negocio)->create(['tipo' => 'qr']);

        $this->enAmbasFichas($producto, fn (Assert $p) => $p
            ->where('estadisticas.totales.total', 0)
            ->where('estadisticas.total_historico', 0)
            ->where('estadisticas.varios_enlaces', false)
            ->where('estadisticas.serie', fn ($serie) => count($serie) === 30 && $this->sumaSerie($serie->all()) === 0));
    }

    public function test_un_acceso_coincide_en_resumen_grafica_e_historico(): void
    {
        $producto = Producto::factory()->for($this->negocio)->create(['tipo' => 'qr']);
        $this->acceso($producto, '2026-11-12 09:30');

        $this->enAmbasFichas($producto, fn (Assert $p) => $p
            ->where('estadisticas.totales.total', 1)
            ->where('estadisticas.totales.visitantes_estimados', 1)
            ->where('estadisticas.total_historico', 1)
            ->where('estadisticas.serie', fn ($serie) => $this->sumaSerie($serie->all()) === 1));
    }

    public function test_varios_accesos_en_distintos_dias_con_historico_y_bots(): void
    {
        $producto = Producto::factory()->for($this->negocio)->create(['tipo' => 'qr']);
        $this->acceso($producto, '2026-11-12 08:00', huella: 'a');
        $this->acceso($producto, '2026-11-12 18:00', huella: 'b');
        $this->acceso($producto, '2026-11-07 12:00', huella: 'c');
        $this->acceso($producto, '2026-09-20 12:00', huella: 'd'); // fuera de los 30 días: solo histórico
        $this->acceso($producto, '2026-11-12 10:00', bot: true);   // bot: no cuenta

        $this->enAmbasFichas($producto, fn (Assert $p) => $p
            ->where('estadisticas.totales.total', 3)
            ->where('estadisticas.totales.bots', 1)
            ->where('estadisticas.total_historico', 4)
            ->where('estadisticas.serie', function ($serie) {
                $porDia = array_column($serie->all(), 'total', 'fecha');

                return $this->sumaSerie($serie->all()) === 3 && $porDia['2026-11-12'] === 2 && $porDia['2026-11-07'] === 1;
            }));
    }

    /**
     * Caso reportado: el QR 2 tenía 22 accesos y el resumen mostraba 1
     * porque solo contaba el QR 1.
     */
    public function test_con_dos_qr_el_resumen_suma_ambos_enlaces_igual_que_el_detalle_por_qr(): void
    {
        $producto = $this->dosQr();
        $qr2 = $producto->enlacesSecundarios()->sole();

        $this->acceso($producto, '2026-11-10 12:00', huella: 'q1');
        for ($i = 0; $i < 22; $i++) {
            $this->acceso($qr2, '2026-11-'.(1 + $i % 11).' 15:00', huella: "q2-{$i}");
        }

        $this->enAmbasFichas($producto, fn (Assert $p) => $p
            ->where('estadisticas.totales.total', 23)
            ->where('estadisticas.total_historico', 23)
            ->where('estadisticas.varios_enlaces', true)
            ->where('estadisticas.serie', fn ($serie) => $this->sumaSerie($serie->all()) === 23)
            ->where('estadisticas_medios.0.id', $producto->id)
            ->where('estadisticas_medios.0.totales.total', 1)
            ->where('estadisticas_medios.1.id', $qr2->id)
            ->where('estadisticas_medios.1.totales.total', 22));
    }

    public function test_el_qr2_archivado_conserva_su_historial_en_el_producto(): void
    {
        $producto = $this->dosQr();
        $qr2 = $producto->enlacesSecundarios()->sole();
        $this->acceso($producto, '2026-11-10 12:00');
        $this->acceso($qr2, '2026-11-11 12:00');
        $qr2->forceFill(['archivado_en' => now()])->save();

        $this->enAmbasFichas($producto, fn (Assert $p) => $p
            ->where('estadisticas.totales.total', 2)
            ->where('estadisticas.total_historico', 2));
    }

    public function test_sin_eventos_detallados_los_totales_salen_de_los_resumenes_diarios(): void
    {
        $producto = Producto::factory()->for($this->negocio)->create(['tipo' => 'qr']);
        $this->acceso($producto, '2026-11-05 12:00');
        $this->acceso($producto, '2026-11-06 12:00');

        // Depuración de eventos individuales (retención): los totales diarios
        // se conservan, así que resumen, gráfica e histórico no cambian.
        EventoAcceso::query()->delete();

        $this->enAmbasFichas($producto, fn (Assert $p) => $p
            ->where('estadisticas.totales.total', 2)
            ->where('estadisticas.totales.visitantes_estimados', 0)
            ->where('estadisticas.total_historico', 2)
            ->where('estadisticas.serie', fn ($serie) => $this->sumaSerie($serie->all()) === 2));
    }

    public function test_no_se_mezclan_datos_de_otro_negocio(): void
    {
        $producto = $this->dosQr();
        $ajeno = Producto::factory()->for(Negocio::factory())->create(['tipo' => 'qr']);
        $this->acceso($producto, '2026-11-10 12:00');
        $this->acceso($ajeno, '2026-11-10 12:00');
        $this->acceso($ajeno, '2026-11-11 12:00');

        $this->enAmbasFichas($producto, fn (Assert $p) => $p
            ->where('estadisticas.totales.total', 1)
            ->where('estadisticas.total_historico', 1));

        $this->actingAs($this->cliente)->get(route('cliente.productos.show', $ajeno))->assertNotFound();
    }
}
