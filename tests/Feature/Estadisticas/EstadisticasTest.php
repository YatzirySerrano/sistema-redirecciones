<?php

namespace Tests\Feature\Estadisticas;

use App\Enums\ResultadoAcceso;
use App\Jobs\RegistrarEventoAcceso;
use App\Models\EventoAcceso;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\User;
use App\Services\Configuracion\AjustesPlataforma;
use App\Services\Estadisticas\ClasificadorAgente;
use App\Services\Estadisticas\ConsultasEstadisticas;
use App\Services\Estadisticas\FiltroEstadisticas;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EstadisticasTest extends TestCase
{
    use RefreshDatabase;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->producto = Producto::factory()->create();
    }

    /**
     * Acceso humano redirigido, como lo persiste el job real.
     */
    private function acceso(CarbonImmutable $momento, ?string $huella, ?Producto $producto = null, bool $bot = false): void
    {
        $producto ??= $this->producto;

        (new RegistrarEventoAcceso(
            productoId: $producto->id,
            negocioId: $producto->negocio_id,
            resultado: ResultadoAcceso::Redirigido,
            ocurridoEn: $momento->toDateTimeString(),
            agente: $bot ? 'curl/8.0' : 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Safari/604.1',
            huellaDiaria: $huella,
        ))->handle(app(ClasificadorAgente::class));
    }

    public function test_visitantes_estimados_cuenta_huellas_diarias_distintas(): void
    {
        $hoy = CarbonImmutable::now()->startOfHour();
        $this->acceso($hoy, 'huella-a');
        $this->acceso($hoy->addMinute(), 'huella-a');
        $this->acceso($hoy->addMinutes(2), 'huella-b');
        $this->acceso($hoy->addMinutes(3), 'huella-c', bot: true);

        $totales = app(ConsultasEstadisticas::class)->totales(FiltroEstadisticas::ultimosDias(7));

        $this->assertSame(3, $totales['total']);
        $this->assertSame(1, $totales['bots']);
        $this->assertSame(2, $totales['visitantes_estimados']);
        $this->assertFalse($totales['visitantes_parcial']);
    }

    public function test_los_visitantes_se_marcan_parciales_o_no_disponibles_fuera_de_la_conservacion(): void
    {
        config(['dasti.dias_conservacion_huella' => 30]);
        $this->acceso(CarbonImmutable::now()->subDays(100), null);
        $this->acceso(CarbonImmutable::now()->subDays(2), 'reciente');

        $consultas = app(ConsultasEstadisticas::class);
        $anio = $consultas->totales(FiltroEstadisticas::ultimosDias(365));
        $this->assertSame(2, $anio['total']);
        $this->assertSame(1, $anio['visitantes_estimados']);
        $this->assertTrue($anio['visitantes_parcial']);
        $this->assertSame(CarbonImmutable::today()->subDays(29)->toDateString(), $anio['visitantes_desde']);

        $antiguo = new FiltroEstadisticas(CarbonImmutable::today()->subDays(120), CarbonImmutable::today()->subDays(90)->endOfDay());
        $periodo = $consultas->totales($antiguo);
        $this->assertSame(1, $periodo['total']);
        $this->assertNull($periodo['visitantes_estimados'], 'Un periodo sin huellas no debe mostrar 0 visitantes.');
        $this->assertNull($periodo['visitantes_desde']);
    }

    public function test_los_contadores_no_retroceden_con_eventos_en_desorden(): void
    {
        $reciente = CarbonImmutable::now()->startOfMinute();
        $this->acceso($reciente, 'x');
        $this->acceso($reciente->subHour(), 'y');

        $producto = $this->producto->fresh();
        $this->assertSame(2, $producto->total_accesos);
        $this->assertSame($reciente->toDateTimeString(), $producto->ultimo_acceso_en->toDateTimeString());
    }

    public function test_un_evento_en_espera_no_altera_los_contadores_del_nuevo_propietario(): void
    {
        $origen = $this->producto->negocio_id;
        $nuevo = Negocio::factory()->create();
        $this->producto->forceFill(['negocio_id' => $nuevo->id, 'total_accesos' => 0, 'ultimo_acceso_en' => null])->save();

        // Evento capturado antes de la transferencia (negocio de origen).
        (new RegistrarEventoAcceso($this->producto->id, $origen, ResultadoAcceso::Redirigido, now()->toDateTimeString(), 'Mozilla/5.0 (iPhone) Safari', 'h'))
            ->handle(app(ClasificadorAgente::class));

        $this->assertSame(0, $this->producto->fresh()->total_accesos);
        $this->assertNull($this->producto->fresh()->ultimo_acceso_en);
        $this->assertSame(1, (int) DB::table('accesos_diarios')->where('negocio_id', $origen)->sum('total'));
        $this->assertSame(1, EventoAcceso::query()->where('negocio_id', $origen)->count());
    }

    public function test_con_estadisticas_deshabilitadas_el_cliente_no_recibe_contadores(): void
    {
        $cliente = User::factory()->cliente($this->producto->negocio)->create();
        $this->acceso(CarbonImmutable::now(), 'h');
        app(AjustesPlataforma::class)->guardar(['clientes_ven_estadisticas' => false]);

        $this->actingAs($cliente)->get(route('cliente.inicio'))
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina
                ->where('indicadores.accesos_totales', null)
                ->where('indicadores.accesos_30_dias', null)
                ->where('serie', null)
                ->where('productos.data.0.total_accesos', null)
                ->where('productos.data.0.ultimo_acceso_en', null));

        $this->actingAs($cliente)->get(route('cliente.productos.index'))
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('productos.data.0.total_accesos', null));
        $this->actingAs($cliente)->get(route('cliente.productos.show', $this->producto))
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('estadisticas', null)->where('producto.data.total_accesos', null));
        $this->actingAs($cliente)->get(route('cliente.estadisticas.index'))->assertForbidden();

        // El administrador sigue viendo los datos.
        $this->actingAs(User::factory()->create())->get(route('admin.productos.show', $this->producto))
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('producto.data.total_accesos', 1));
    }

    public function test_tipos_de_producto_sin_atribuir_canal_a_los_combinados(): void
    {
        $combinado = Producto::factory()->create(['tipo' => 'combinado']);
        $this->acceso(CarbonImmutable::now(), 'a', $combinado);
        $this->acceso(CarbonImmutable::now(), 'b');

        $porTipo = collect(app(ConsultasEstadisticas::class)->porTipo(FiltroEstadisticas::ultimosDias(7)))->keyBy('clave');

        $this->assertSame(1, $porTipo['nfc']['total']);
        $this->assertSame(0, $porTipo['qr']['total']);
        $this->assertSame(1, $porTipo['combinado']['total']);
        $this->assertSame('Combinado (canal no distinguible)', $porTipo['combinado']['etiqueta']);
    }
}
