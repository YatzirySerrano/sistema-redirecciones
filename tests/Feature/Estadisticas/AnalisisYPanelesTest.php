<?php

namespace Tests\Feature\Estadisticas;

use App\Enums\EstadoPago;
use App\Enums\ResultadoAcceso;
use App\Jobs\RegistrarEventoAcceso;
use App\Models\Negocio;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Estadisticas\ClasificadorAgente;
use App\Services\Estadisticas\ConsultasEstadisticas;
use App\Services\Estadisticas\FiltroEstadisticas;
use App\Services\Estadisticas\IndicadoresPlataforma;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Análisis de interacciones (series por canal, días, horarios, productos),
 * paneles y aislamiento entre negocios.
 *
 * Los accesos de estas pruebas son datos sintéticos creados en la base de
 * pruebas aislada (RefreshDatabase) con el mismo job que usa la redirección
 * real; nunca se insertan en la base principal.
 */
class AnalisisYPanelesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['dasti.cache_estadisticas_segundos' => 0]);
    }

    private function acceso(Producto $producto, CarbonImmutable $momento, ResultadoAcceso $resultado = ResultadoAcceso::Redirigido, bool $bot = false, string $huella = 'h'): void
    {
        (new RegistrarEventoAcceso(
            productoId: $producto->id,
            negocioId: $producto->negocio_id,
            resultado: $resultado,
            ocurridoEn: $momento->toDateTimeString(),
            agente: $bot ? 'curl/8.0' : 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Safari/604.1',
            huellaDiaria: $huella,
        ))->handle(app(ClasificadorAgente::class));
    }

    /**
     * Tarjeta combinada con destinos independientes: frente NFC + reverso QR.
     *
     * @return array{0: Producto, 1: Producto}
     */
    private function combinadoIndependiente(Negocio $negocio): array
    {
        $frente = Producto::factory()->for($negocio)->create(['tipo' => 'combinado', 'medio' => 'nfc', 'nombre' => 'Tarjeta doble']);
        $reverso = Producto::factory()->for($negocio)->create(['tipo' => 'combinado', 'medio' => 'qr', 'producto_principal_id' => $frente->id, 'nombre' => 'Tarjeta doble (reverso)']);

        return [$frente, $reverso];
    }

    private function consultas(): ConsultasEstadisticas
    {
        return app(ConsultasEstadisticas::class);
    }

    // ---- Periodos ----

    public function test_el_periodo_anterior_tiene_la_misma_duracion_y_termina_justo_antes(): void
    {
        $filtro = new FiltroEstadisticas(CarbonImmutable::parse('2026-06-01')->startOfDay(), CarbonImmutable::parse('2026-06-30')->endOfDay(), 5, canal: 'qr', productoFisicoId: 9);
        $anterior = $filtro->anterior();

        $this->assertSame(30, $filtro->dias());
        $this->assertSame(30, $anterior->dias());
        $this->assertSame('2026-05-02', $anterior->desde->toDateString());
        $this->assertSame('2026-05-31', $anterior->hasta->toDateString());
        $this->assertSame([5, 'qr', 9], [$anterior->negocioId, $anterior->canal, $anterior->productoFisicoId]);

        $hoy = FiltroEstadisticas::ultimosDias(1);
        $this->assertSame(CarbonImmutable::yesterday()->toDateString(), $hoy->anterior()->desde->toDateString());
    }

    // ---- Canales ----

    public function test_la_serie_reparte_cada_dia_por_canal_segun_el_enlace(): void
    {
        $negocio = Negocio::factory()->create();
        [$frente, $reverso] = $this->combinadoIndependiente($negocio);
        $qr = Producto::factory()->for($negocio)->qr()->create();
        $compartido = Producto::factory()->for($negocio)->create(['tipo' => 'combinado']);
        $hoy = CarbonImmutable::now()->startOfHour();

        $this->acceso($frente, $hoy);
        $this->acceso($frente, $hoy);
        $this->acceso($reverso, $hoy);
        $this->acceso($qr, $hoy);
        $this->acceso($compartido, $hoy);
        $this->acceso($qr, $hoy, bot: true);

        $serie = $this->consultas()->serieCanales(FiltroEstadisticas::ultimosDias(7, $negocio->id));
        $dia = collect($serie)->firstWhere('fecha', $hoy->toDateString());

        $this->assertCount(7, $serie);
        $this->assertSame(['fecha' => $hoy->toDateString(), 'total' => 5, 'nfc' => 2, 'qr' => 2, 'combinado' => 1], $dia);
        $this->assertSame(0, collect($serie)->where('fecha', '!=', $hoy->toDateString())->sum('total'));
    }

    public function test_filtro_por_canal_y_por_producto_fisico(): void
    {
        $negocio = Negocio::factory()->create();
        [$frente, $reverso] = $this->combinadoIndependiente($negocio);
        $otro = Producto::factory()->for($negocio)->qr()->create();
        $hoy = CarbonImmutable::now()->startOfHour();

        $this->acceso($frente, $hoy);
        $this->acceso($reverso, $hoy);
        $this->acceso($reverso, $hoy);
        $this->acceso($otro, $hoy);

        $base = FiltroEstadisticas::ultimosDias(7, $negocio->id);
        $qr = new FiltroEstadisticas($base->desde, $base->hasta, $negocio->id, canal: 'qr');
        $fisico = new FiltroEstadisticas($base->desde, $base->hasta, $negocio->id, productoFisicoId: $frente->id);

        $this->assertSame(3, $this->consultas()->totales($qr)['total']);
        // El producto físico incluye su reverso QR.
        $this->assertSame(3, $this->consultas()->totales($fisico)['total']);

        $top = $this->consultas()->actividadPorProducto($base);
        $this->assertCount(2, $top);
        $this->assertSame(['id' => $frente->id, 'total' => 3, 'nfc' => 1, 'qr' => 2, 'combinado' => 0], array_intersect_key($top[0], array_flip(['id', 'total', 'nfc', 'qr', 'combinado'])));
    }

    // ---- Días y horarios ----

    public function test_promedio_por_dia_de_la_semana_considera_cuantas_veces_aparece(): void
    {
        $serie = [
            ['fecha' => '2026-06-01', 'total' => 4], // lunes
            ['fecha' => '2026-06-08', 'total' => 2], // lunes
            ['fecha' => '2026-06-02', 'total' => 3], // martes
        ];

        $dias = collect($this->consultas()->porDiaSemana($serie))->keyBy('dia');

        $this->assertSame(['dia' => 1, 'etiqueta' => 'Lunes', 'total' => 6, 'dias' => 2, 'promedio' => 3.0], $dias[1]);
        $this->assertSame(3.0, $dias[2]['promedio']);
        $this->assertSame(0, $dias[7]['dias']);
        $this->assertSame(0.0, $dias[7]['promedio']);
    }

    public function test_mapa_horario_usa_la_hora_local_registrada(): void
    {
        $producto = Producto::factory()->create();
        // Hora local de la plataforma (America/Mexico_City), tal como se guarda.
        $lunes = CarbonImmutable::now()->startOfWeek()->setTime(10, 15);
        $lunes = $lunes->isFuture() ? $lunes->subWeek() : $lunes;
        $this->acceso($producto, $lunes);
        $this->acceso($producto, $lunes->addMinutes(20));
        $this->acceso($producto, $lunes->addDays(2)->setTime(21, 5));
        $this->acceso($producto, $lunes->setTime(10, 30), bot: true);

        $mapa = $this->consultas()->mapaHorario(FiltroEstadisticas::ultimosDias(14, $producto->negocio_id));

        $this->assertTrue($mapa['disponible']);
        $this->assertSame(3, $mapa['total']);
        $this->assertContains(['dia' => 1, 'hora' => 10, 'total' => 2], $mapa['celdas']);
        $this->assertContains(['dia' => 3, 'hora' => 21, 'total' => 1], $mapa['celdas']);
        $this->assertSame(2, $mapa['por_hora'][10]);
        $this->assertCount(24, $mapa['por_hora']);
    }

    public function test_el_detalle_no_se_inventa_fuera_de_la_retencion(): void
    {
        $antiguo = new FiltroEstadisticas(CarbonImmutable::today()->subDays(800), CarbonImmutable::today()->subDays(700)->endOfDay());

        $this->assertNull($this->consultas()->coberturaDetalle($antiguo));
        $this->assertFalse($this->consultas()->mapaHorario($antiguo)['disponible']);
        $this->assertNull($this->consultas()->analisis($antiguo)['cobertura']['detalle_desde']);
    }

    // ---- Incidencias e inactividad ----

    public function test_accesos_que_no_llegaron_a_destino_se_cuentan_aparte(): void
    {
        $producto = Producto::factory()->create();
        $ahora = CarbonImmutable::now()->startOfHour();
        $this->acceso($producto, $ahora, ResultadoAcceso::SinDestino);
        $this->acceso($producto, $ahora, ResultadoAcceso::SinDestino);
        $this->acceso($producto, $ahora, ResultadoAcceso::Suspendido);
        $this->acceso($producto, $ahora, ResultadoAcceso::SinDestino, bot: true);

        $filtro = FiltroEstadisticas::ultimosDias(7, $producto->negocio_id);
        $fallidos = collect($this->consultas()->sinRedirigir($filtro))->keyBy('clave');

        $this->assertSame(2, $fallidos['sin_destino']['total']);
        $this->assertSame(1, $fallidos['suspendido']['total']);
        $this->assertSame(0, $this->consultas()->totales($filtro)['total']);
    }

    public function test_productos_y_negocios_sin_actividad(): void
    {
        $negocio = Negocio::factory()->create();
        [$frente, $reverso] = $this->combinadoIndependiente($negocio);
        $quieto = Producto::factory()->for($negocio)->create(['nombre' => 'Sin uso']);
        Producto::factory()->for($negocio)->suspendido()->create();
        $inactivo = Negocio::factory()->create();
        Producto::factory()->for($inactivo)->create();

        // Solo el reverso se usó: el producto físico cuenta como activo.
        $this->acceso($reverso, CarbonImmutable::now()->startOfHour());

        $filtro = FiltroEstadisticas::ultimosDias(30, $negocio->id);
        $sin = $this->consultas()->productosSinActividad($filtro);

        $this->assertSame(1, $sin['total']);
        $this->assertSame($quieto->id, $sin['productos'][0]['id']);
        $this->assertNull($sin['productos'][0]['ultimo_acceso_en']);
        $this->assertSame(1, $this->consultas()->negociosSinActividad(FiltroEstadisticas::ultimosDias(30)));
    }

    // ---- Cliente ----

    public function test_estadisticas_del_cliente_sin_productos_ni_actividad(): void
    {
        $cliente = User::factory()->cliente()->create();

        $this->actingAs($cliente)->get(route('cliente.estadisticas.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('cliente/estadisticas/Index')
                ->where('tiene_productos', false)
                ->where('totales.total', 0)
                ->where('anterior.total', 0)
                ->has('serie', 30)
                ->where('productos_top', [])
                ->where('sin_actividad.total', 0));
    }

    public function test_estadisticas_del_cliente_con_filtros_y_comparacion(): void
    {
        $negocio = Negocio::factory()->create();
        $cliente = User::factory()->cliente($negocio)->create();
        $nfc = Producto::factory()->for($negocio)->create();
        $qr = Producto::factory()->for($negocio)->qr()->create();
        $hoy = CarbonImmutable::today()->setTime(12, 0);

        $this->acceso($nfc, $hoy);
        $this->acceso($nfc, $hoy);
        $this->acceso($qr, $hoy);
        // Periodo anterior equivalente (7 días previos).
        $this->acceso($nfc, $hoy->subDays(9));

        $desde = $hoy->subDays(6)->toDateString();
        $hasta = $hoy->toDateString();

        $this->actingAs($cliente)->get(route('cliente.estadisticas.index', ['desde' => $desde, 'hasta' => $hasta]))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('totales.total', 3)
                ->where('anterior.total', 1)
                ->where('anterior.hasta', $hoy->subDays(7)->toDateString())
                ->has('serie', 7)
                ->has('serie_anterior', 7)
                ->where('canales_disponibles', fn ($c) => collect($c)->sort()->values()->all() === ['nfc', 'qr'])
                ->has('dias_semana', 7)
                ->where('horario.total', 3));

        $this->actingAs($cliente)->get(route('cliente.estadisticas.index', ['desde' => $desde, 'hasta' => $hasta, 'canal' => 'qr']))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('totales.total', 1)->where('filtros.canal', 'qr')->where('sin_actividad', null));

        $this->actingAs($cliente)->get(route('cliente.estadisticas.index', ['desde' => $desde, 'hasta' => $hasta, 'producto_id' => $nfc->id]))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('totales.total', 2)->where('filtros.producto_id', (string) $nfc->id));

        $this->actingAs($cliente)->get(route('cliente.estadisticas.index', ['canal' => 'telepatia']))
            ->assertSessionHasErrors('canal');
    }

    public function test_un_cliente_no_puede_consultar_productos_de_otro_negocio(): void
    {
        $mio = Negocio::factory()->create();
        $ajeno = Negocio::factory()->create();
        $cliente = User::factory()->cliente($mio)->create();
        $propio = Producto::factory()->for($mio)->create();
        $deOtro = Producto::factory()->for($ajeno)->create();
        $hoy = CarbonImmutable::now()->startOfHour();

        $this->acceso($propio, $hoy);
        $this->acceso($deOtro, $hoy);
        $this->acceso($deOtro, $hoy);

        // El identificador ajeno se ignora: se muestra solo lo propio.
        $this->actingAs($cliente)->get(route('cliente.estadisticas.index', ['producto_id' => $deOtro->id, 'negocio_id' => $ajeno->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('filtros.producto_id', '')
                ->where('totales.total', 1)
                ->where('productos_top', fn ($top) => collect($top)->pluck('id')->all() === [$propio->id])
                ->where('productos', fn ($opciones) => collect($opciones)->pluck('value')->all() === [(string) $propio->id]));

        $this->actingAs($cliente)->get(route('cliente.inicio'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('indicadores.accesos_30_dias.total', 1)
                ->where('productos_top', fn ($top) => collect($top)->pluck('id')->all() === [$propio->id]));

        // Las estadísticas de la plataforma no están disponibles para clientes.
        $this->actingAs($cliente)->get(route('admin.estadisticas.index'))->assertForbidden();
        $this->actingAs($cliente)->get(route('admin.panel'))->assertForbidden();
    }

    public function test_inicio_del_cliente_resume_canales_producto_destacado_y_alertas(): void
    {
        $negocio = Negocio::factory()->create();
        $cliente = User::factory()->cliente($negocio)->create();
        $nfc = Producto::factory()->for($negocio)->create(['nombre' => 'Mostrador']);
        Producto::factory()->for($negocio)->qr()->create(['nombre' => 'Mesa 4']);
        $hoy = CarbonImmutable::now()->startOfHour();

        $this->acceso($nfc, $hoy);
        $this->acceso($nfc, $hoy, ResultadoAcceso::SinDestino);

        $this->actingAs($cliente)->get(route('cliente.inicio'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('ver_estadisticas', true)
                ->where('productos_top.0.nombre', 'Mostrador')
                ->where('canales', fn ($c) => collect($c)->firstWhere('clave', 'nfc')['total'] === 1)
                ->where('sin_actividad.total', 1)
                ->where('alertas', fn ($a) => collect($a)->pluck('clave')->contains('sin_redirigir'))
                ->has('serie', 30)
                ->has('serie_anterior', 30));
    }

    // ---- Administración ----

    public function test_estadisticas_de_la_plataforma_por_negocio_y_canal(): void
    {
        $admin = User::factory()->superadministrador()->create();
        $a = Negocio::factory()->create();
        $b = Negocio::factory()->create();
        $hoy = CarbonImmutable::now()->startOfHour();
        $this->acceso(Producto::factory()->for($a)->create(), $hoy);
        $this->acceso(Producto::factory()->for($b)->qr()->create(), $hoy);

        $this->actingAs($admin)->get(route('admin.estadisticas.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('vista', 'uso')
                ->where('uso.totales.total', 2)
                ->has('uso.negocios_top', 2)
                ->where('puede_comercial', true));

        $this->actingAs($admin)->get(route('admin.estadisticas.index', ['negocio_id' => $a->id]))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('uso.totales.total', 1));

        $this->actingAs($admin)->get(route('admin.estadisticas.index', ['canal' => 'qr']))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('uso.totales.total', 1));
    }

    public function test_la_vista_comercial_exige_permiso_de_servicios_o_pagos(): void
    {
        $limitado = User::factory()->administrador()->conRol($this->rolPlataforma(['panel.ver', 'estadisticas.ver']))->create();

        $this->actingAs($limitado)->get(route('admin.estadisticas.index', ['vista' => 'comercial']))->assertForbidden();
        $this->actingAs($limitado)->get(route('admin.estadisticas.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('puede_comercial', false)->missing('comercial'));

        $this->actingAs($limitado)->get(route('admin.panel'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('ingresos', null)
                ->where('contrataciones', null)
                ->where('usuarios', null)
                ->where('crecimiento', null));
    }

    public function test_los_ingresos_solo_cuentan_pagos_cobrados_reales_menos_reembolsos(): void
    {
        $negocio = Negocio::factory()->create();
        $servicio = Servicio::query()->where('clave', Servicio::FIDELIDAD)->firstOrFail();
        $pago = fn (array $datos) => Pago::query()->create([
            'negocio_id' => $negocio->id,
            'servicio_id' => $servicio->id,
            'moneda' => 'MXN',
            'importe_centavos' => 100000,
            ...$datos,
        ]);

        $pago(['proveedor' => 'openpay', 'estado' => EstadoPago::Pagado, 'livemode' => true, 'pagado_en' => now()]);
        $pago(['proveedor' => 'manual', 'estado' => EstadoPago::Pagado, 'livemode' => false, 'pagado_en' => now()]);
        $pago(['proveedor' => 'openpay', 'estado' => EstadoPago::ReembolsoParcial, 'livemode' => true, 'pagado_en' => now(), 'reembolsado_centavos' => 40000]);
        // No son ingresos: de prueba, pendiente y fallido.
        $pago(['proveedor' => 'openpay', 'estado' => EstadoPago::Pagado, 'livemode' => false, 'pagado_en' => now()]);
        $pago(['proveedor' => 'openpay', 'estado' => EstadoPago::Pendiente, 'livemode' => true]);
        $pago(['proveedor' => 'openpay', 'estado' => EstadoPago::Fallido, 'livemode' => true]);

        $ingresos = app(IndicadoresPlataforma::class)->ingresos(FiltroEstadisticas::ultimosDias(30));

        $this->assertSame(3, $ingresos['pagos']);
        $this->assertSame(300000, $ingresos['bruto']);
        $this->assertSame(40000, $ingresos['reembolsado']);
        $this->assertSame(260000, $ingresos['neto']);
        $this->assertSame(0, $ingresos['neto_anterior']);
        $this->assertSame(1, $ingresos['pagos_de_prueba']);
        $this->assertSame(1, $ingresos['pendientes_total']);
        $this->assertSame(260000, collect($ingresos['serie_mensual'])->last()['total']);

        $admin = User::factory()->superadministrador()->create();
        $this->actingAs($admin)->get(route('admin.estadisticas.index', ['vista' => 'comercial']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('comercial.ingresos.neto', 260000));
    }

    public function test_el_panel_administrativo_incluye_indicadores_de_la_plataforma(): void
    {
        $admin = User::factory()->superadministrador()->create();
        $negocio = Negocio::factory()->create();
        Producto::factory()->for($negocio)->count(2)->create();
        Producto::factory()->for($negocio)->qr()->suspendido()->create();
        User::factory()->cliente($negocio)->create(['invitacion_enviada_en' => now(), 'invitacion_fallida_en' => now()]);

        $this->actingAs($admin)->get(route('admin.panel'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('productos.total', 3)
                ->where('productos.suspendidos', 1)
                ->where('productos.por_tipo', fn ($t) => collect($t)->firstWhere('clave', 'nfc')['total'] === 2)
                ->where('negocios.nuevos', 1)
                ->where('usuarios.invitaciones_fallidas', 1)
                ->has('crecimiento', 12)
                ->has('serie', 30)
                ->has('contrataciones.por_servicio')
                ->has('ingresos'));
    }

    /**
     * @param  list<string>  $permisos
     */
    private function rolPlataforma(array $permisos): Rol
    {
        $rol = new Rol;
        $rol->forceFill(['name' => 'Consulta '.uniqid(), 'guard_name' => 'web', 'ambito' => 'plataforma'])->save();
        $rol->syncPermissions(Permission::query()->whereIn('name', $permisos)->get());

        return $rol;
    }

    public function test_las_consultas_no_cargan_eventos_individuales_al_servidor_web(): void
    {
        // Los totales y la serie salen del agregado diario: borrar los eventos
        // individuales (depuración) no cambia los totales históricos.
        $producto = Producto::factory()->create();
        $this->acceso($producto, CarbonImmutable::now()->startOfHour());
        DB::table('eventos_acceso')->delete();

        $filtro = FiltroEstadisticas::ultimosDias(7, $producto->negocio_id);
        $this->assertSame(1, $this->consultas()->totales($filtro)['total']);
        $this->assertSame(1, array_sum(array_column($this->consultas()->serieCanales($filtro), 'total')));
        $this->assertSame(0, $this->consultas()->mapaHorario($filtro)['total']);
    }
}
