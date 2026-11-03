<?php

namespace Tests\Feature\Fidelidad;

use App\Enums\EstadoRecompensa;
use App\Enums\TipoMovimiento;
use App\Models\Fidelidad\Movimiento;
use App\Models\Fidelidad\Participacion;
use App\Models\Fidelidad\Recompensa;
use App\Models\Negocio;
use App\Models\User;
use App\Services\Fidelidad\BuscadorConsumidores;
use App\Services\Fidelidad\RegistroVisitas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\PreparaFidelidad;
use Tests\TestCase;

/**
 * Búsqueda en tiempo real del mostrador (Tarea B): nunca registra visitas;
 * la visita solo se registra al seleccionar y confirmar.
 */
class BusquedaMostradorTest extends TestCase
{
    use PreparaFidelidad, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function buscar(User $usuario, string $q, array $extra = [])
    {
        return $this->actingAs($usuario)->getJson(route('cliente.fidelidad.mostrador.identificar', ['q' => $q, ...$extra]));
    }

    public function test_nombre_sin_distinguir_mayusculas_ni_acentos_y_por_palabras(): void
    {
        $negocio = $this->negocioConFidelidad();
        $jose = $this->consumidor($negocio, ['nombre' => 'José Ángel Muñoz']);
        $this->consumidor($negocio, ['nombre' => 'Beatriz Ruiz']);
        $empleado = $this->empleado($negocio);

        foreach (['jose', 'JOSÉ', 'angel munoz', 'muñ jos', 'Ángel'] as $q) {
            $this->buscar($empleado, $q)->assertOk()
                ->assertJsonCount(1, 'resultados')
                ->assertJsonPath('resultados.0.id', $jose->id)
                ->assertJsonPath('resultados.0.coincidencia', 'nombre')
                // Nombre/alias nunca se preseleccionan.
                ->assertJsonPath('preseleccion', null);
        }

        $this->buscar($empleado, 'zzzz')->assertOk()->assertJsonCount(0, 'resultados')
            ->assertJsonPath('mensaje', 'Sin resultados. Revisa lo que escribiste o registra al cliente.');
    }

    public function test_por_alias(): void
    {
        $negocio = $this->negocioConFidelidad();
        $c = $this->consumidor($negocio, ['nombre' => 'María Fernanda Ríos', 'alias' => 'Mafer']);

        $this->buscar($this->empleado($negocio), 'mafer')->assertOk()->assertJsonPath('resultados.0.id', $c->id)
            ->assertJsonPath('resultados.0.alias', 'Mafer');
    }

    public function test_telefono_solo_digitos_con_formato_y_lada_y_enmascarado(): void
    {
        $negocio = $this->negocioConFidelidad();
        $c = $this->consumidor($negocio, ['nombre' => 'Lucía', 'telefono' => '5512345678', 'email' => 'lucia@correo.test']);
        $empleado = $this->empleado($negocio);

        foreach (['55 1234 5678', '+52 (55) 1234-5678', '5678', '1234-56'] as $q) {
            $respuesta = $this->buscar($empleado, $q)->assertOk()->assertJsonPath('resultados.0.id', $c->id);
            $this->assertSame('••••5678', $respuesta->json('resultados.0.telefono'));
            $this->assertStringNotContainsString('5512345678', $respuesta->getContent());
            $this->assertStringNotContainsString('lucia@correo.test', $respuesta->getContent());
        }

        $this->buscar($empleado, '5512345678')->assertJsonPath('resultados.0.coincidencia', 'telefono_exacto')
            ->assertJsonPath('preseleccion', null);
        // Muy pocos dígitos: se pide escribir más.
        $this->buscar($empleado, '55')->assertOk()->assertJsonCount(0, 'resultados')
            ->assertJsonPath('mensaje', 'Escribe al menos 4 dígitos del teléfono.');
    }

    public function test_codigo_exacto_se_preselecciona_y_admite_la_url_del_qr(): void
    {
        $negocio = $this->negocioConFidelidad();
        $c = $this->consumidor($negocio);
        $tarjeta = $this->tarjeta($c);
        $empleado = $this->empleado($negocio);

        foreach ([$tarjeta->codigo, strtoupper($tarjeta->codigo), $tarjeta->urlPublica()] as $q) {
            $this->buscar($empleado, $q)->assertOk()
                ->assertJsonPath('preseleccion', $c->id)
                ->assertJsonPath('resultados.0.coincidencia', 'codigo')
                ->assertJsonPath('credencial.codigo', $tarjeta->codigo);
        }
    }

    public function test_relevancia_y_varias_coincidencias_se_distinguen(): void
    {
        $negocio = $this->negocioConFidelidad();
        $contiene = $this->consumidor($negocio, ['nombre' => 'Rosa Ana Pérez', 'telefono' => '5511110001']);
        $prefijo = $this->consumidor($negocio, ['nombre' => 'Ana López', 'telefono' => '5511110002']);
        $palabra = $this->consumidor($negocio, ['nombre' => 'Luz Anabel', 'telefono' => '5511110003']);
        $dentro = $this->consumidor($negocio, ['nombre' => 'Mariana Soto', 'telefono' => '5511110004']);

        $respuesta = $this->buscar($this->empleado($negocio), 'ana')->assertOk()->assertJsonCount(4, 'resultados');
        $ids = array_column($respuesta->json('resultados'), 'id');

        // Empieza con > palabra que empieza (orden alfabético) > contiene.
        $this->assertSame($prefijo->id, $ids[0]);
        $this->assertEqualsCanonicalizing([$palabra->id, $contiene->id], [$ids[1], $ids[2]]);
        $this->assertSame($dentro->id, $ids[3]);
        $this->assertNull($respuesta->json('preseleccion'));
        $telefonos = array_column($respuesta->json('resultados'), 'telefono');
        $this->assertCount(4, array_unique($telefonos), 'Cada homónimo se distingue por su teléfono enmascarado');
    }

    public function test_telefono_exacto_va_antes_que_coincidencias_parciales(): void
    {
        $negocio = $this->negocioConFidelidad();
        $parcial = $this->consumidor($negocio, ['nombre' => 'Aarón', 'telefono' => '5599998888']);
        $exacto = $this->consumidor($negocio, ['nombre' => 'Zoe', 'telefono' => '9998888555']);

        $ids = array_column($this->buscar($this->empleado($negocio), '9998888555')->json('resultados'), 'id');
        $this->assertSame([$exacto->id], $ids);

        $ids = array_column($this->buscar($this->empleado($negocio), '99988')->json('resultados'), 'id');
        $this->assertEqualsCanonicalizing([$parcial->id, $exacto->id], $ids);
    }

    public function test_limite_y_paginacion(): void
    {
        $negocio = $this->negocioConFidelidad();

        for ($i = 0; $i < BuscadorConsumidores::POR_PAGINA + 3; $i++) {
            $this->consumidor($negocio, ['nombre' => sprintf('Carla %02d', $i)]);
        }

        $empleado = $this->empleado($negocio);
        $this->buscar($empleado, 'carla')->assertOk()
            ->assertJsonCount(BuscadorConsumidores::POR_PAGINA, 'resultados')->assertJsonPath('hay_mas', true);
        $this->buscar($empleado, 'carla', ['pagina' => 2])->assertOk()
            ->assertJsonCount(3, 'resultados')->assertJsonPath('hay_mas', false);
        // Texto demasiado corto: sin consulta a la base y con mensaje.
        $this->buscar($empleado, 'c')->assertOk()->assertJsonCount(0, 'resultados');
        $this->buscar($empleado, str_repeat('a', 300))->assertUnprocessable();
    }

    public function test_aislamiento_entre_negocios(): void
    {
        $negocio = $this->negocioConFidelidad();
        $otro = $this->negocioConFidelidad();
        $ajeno = $this->consumidor($otro, ['nombre' => 'Ana Ajena', 'telefono' => '5500000001']);
        $tarjetaAjena = $this->tarjeta($ajeno);
        $empleado = $this->empleado($negocio);

        $this->buscar($empleado, 'ajena')->assertOk()->assertJsonCount(0, 'resultados');
        $this->buscar($empleado, '5500000001')->assertOk()->assertJsonCount(0, 'resultados');
        $this->buscar($empleado, $tarjetaAjena->urlPublica())->assertOk()->assertJsonCount(0, 'resultados')
            ->assertJsonPath('mensaje', 'Esta tarjeta no pertenece a tu negocio o no existe.');
        $this->actingAs($empleado)->getJson(route('cliente.fidelidad.mostrador.consumidor', $ajeno))->assertNotFound();
    }

    public function test_requiere_permiso_y_servicio(): void
    {
        $negocio = $this->negocioConFidelidad();
        $sinPermiso = User::factory()->cliente($negocio)->conRol($this->rolNegocio('Recepción', ['panel.ver']))->create();

        $this->buscar($sinPermiso, 'ana')->assertForbidden();

        $sinServicio = $this->empleado(Negocio::factory()->create());
        $this->buscar($sinServicio, 'ana')->assertForbidden();

        auth()->logout();
        $this->getJson(route('cliente.fidelidad.mostrador.identificar', ['q' => 'ana']))->assertUnauthorized();
    }

    public function test_buscar_y_abrir_la_ficha_nunca_crea_movimientos(): void
    {
        $negocio = $this->negocioConFidelidad();
        $this->campana($negocio);
        $c = $this->consumidor($negocio, ['nombre' => 'Ana López']);
        $tarjeta = $this->tarjeta($c);
        $empleado = $this->empleado($negocio);
        $antes = [Movimiento::query()->count(), Participacion::query()->count(), Recompensa::query()->count()];

        $this->buscar($empleado, 'ana')->assertOk();
        $this->buscar($empleado, $tarjeta->codigo)->assertOk();
        $this->actingAs($empleado)->getJson(route('cliente.fidelidad.mostrador.consumidor', $c))->assertOk()
            ->assertJsonPath('consumidor.id', $c->id);

        $this->assertSame($antes, [Movimiento::query()->count(), Participacion::query()->count(), Recompensa::query()->count()]);
    }

    public function test_seleccionar_y_confirmar_registra_una_sola_visita_aun_con_doble_envio(): void
    {
        $negocio = $this->negocioConFidelidad();
        $campana = $this->campana($negocio, ['meta_visitas' => 4]);
        $c = $this->consumidor($negocio);
        $tarjeta = $this->tarjeta($c);
        $empleado = $this->empleado($negocio);

        $id = $this->buscar($empleado, $tarjeta->urlPublica())->json('preseleccion');
        $this->assertSame($c->id, $id);
        $uuid = $this->uuid();
        $datos = ['consumidor_id' => $id, 'campana_id' => $campana->id, 'operacion_uuid' => $uuid, 'credencial' => $tarjeta->codigo];

        $this->actingAs($empleado)->postJson(route('cliente.fidelidad.visitas.store'), $datos)->assertOk()
            ->assertJsonPath('repetida', false)->assertJsonPath('consumidor.progreso.0.visitas', 1);
        $this->actingAs($empleado)->postJson(route('cliente.fidelidad.visitas.store'), $datos)->assertOk()
            ->assertJsonPath('repetida', true);

        $this->assertSame(1, Movimiento::query()->where('tipo', TipoMovimiento::Visita)->count());
        $this->assertSame($tarjeta->id, Movimiento::query()->value('credencial_id'));
    }

    public function test_solicitudes_concurrentes_con_operaciones_distintas_respetan_las_reglas(): void
    {
        $negocio = $this->negocioConFidelidad();
        $campana = $this->campana($negocio, ['max_visitas_por_dia' => 1]);
        $c = $this->consumidor($negocio);
        $empleado = $this->empleado($negocio);

        $this->actingAs($empleado)->postJson(route('cliente.fidelidad.visitas.store'), [
            'consumidor_id' => $c->id, 'campana_id' => $campana->id, 'operacion_uuid' => $this->uuid(),
        ])->assertOk();
        $this->actingAs($empleado)->postJson(route('cliente.fidelidad.visitas.store'), [
            'consumidor_id' => $c->id, 'campana_id' => $campana->id, 'operacion_uuid' => $this->uuid(),
        ])->assertUnprocessable()->assertJsonValidationErrors('visita');

        $this->assertSame(1, Participacion::query()->value('visitas_ciclo'));
    }

    public function test_la_meta_genera_recompensa_y_el_canje_es_unico(): void
    {
        $negocio = $this->negocioConFidelidad();
        $campana = $this->campana($negocio, ['meta_visitas' => 2]);
        $c = $this->consumidor($negocio);
        $empleado = $this->empleado($negocio);
        $registro = app(RegistroVisitas::class);

        $registro->registrarVisita($empleado, $c, $campana, $this->uuid());
        $this->travel(1)->days();
        $registro->registrarVisita($empleado, $c->fresh() ?? $c, $campana, $this->uuid());
        $recompensa = Recompensa::query()->firstOrFail();

        $this->buscar($empleado, 'ana')->assertJsonPath('resultados.0.recompensas', 1);

        $uuid = $this->uuid();
        $this->actingAs($empleado)->postJson(route('cliente.fidelidad.recompensas.canje', $recompensa), ['operacion_uuid' => $uuid])->assertOk();
        $this->actingAs($empleado)->postJson(route('cliente.fidelidad.recompensas.canje', $recompensa), ['operacion_uuid' => $this->uuid()])->assertUnprocessable();
        $this->assertSame(EstadoRecompensa::Canjeada, $recompensa->fresh()?->estado);
    }

    public function test_el_mostrador_recibe_la_espera_configurable(): void
    {
        config(['dasti.fidelidad.busqueda_espera_ms' => 450]);
        $negocio = $this->negocioConFidelidad();
        $this->campana($negocio);

        $this->actingAs($this->empleado($negocio))->get(route('cliente.fidelidad.mostrador'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('cliente/fidelidad/Mostrador')
                ->where('busqueda.espera_ms', 450)->where('busqueda.min_caracteres', 2));
    }

    public function test_los_comodines_de_like_se_tratan_como_texto(): void
    {
        $negocio = $this->negocioConFidelidad();
        $this->consumidor($negocio, ['nombre' => 'Ana']);
        $this->consumidor($negocio, ['nombre' => 'Beto']);

        $this->buscar($this->empleado($negocio), '%%')->assertOk()->assertJsonCount(0, 'resultados');
        $this->buscar($this->empleado($negocio), '__')->assertOk()->assertJsonCount(0, 'resultados');
        $this->assertSame(0, DB::table('fidelidad_movimientos')->count());
    }
}
