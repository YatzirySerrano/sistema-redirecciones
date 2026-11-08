<?php

namespace Tests\Feature\Fidelidad;

use App\Enums\EstadoRecompensa;
use App\Enums\TipoMovimiento;
use App\Models\Fidelidad\Movimiento;
use App\Models\Fidelidad\Participacion;
use App\Models\Fidelidad\Recompensa;
use App\Models\Negocio;
use App\Models\User;
use App\Services\Fidelidad\RegistroVisitas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\PreparaFidelidad;
use Tests\TestCase;

class AntifraudeTest extends TestCase
{
    use PreparaFidelidad, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Las páginas públicas cargan recursos de Vite que otros pueden estar
        // recompilando en paralelo.
        $this->withoutVite();
    }

    private function visita(User $usuario, int $consumidorId, int $campanaId, ?string $uuid = null, array $extra = [])
    {
        return $this->actingAs($usuario)->postJson(route('cliente.fidelidad.visitas.store'), [
            'consumidor_id' => $consumidorId,
            'campana_id' => $campanaId,
            'operacion_uuid' => $uuid ?? $this->uuid(),
            ...$extra,
        ]);
    }

    public function test_escanear_la_tarjeta_nunca_suma_visitas_y_no_expone_contacto(): void
    {
        $negocio = $this->negocioConFidelidad();
        $this->campana($negocio);
        $consumidor = $this->consumidor($negocio, ['telefono' => '5512345678', 'email' => 'ana@correo.test']);
        $tarjeta = $this->tarjeta($consumidor);

        for ($i = 0; $i < 3; $i++) {
            $respuesta = $this->get(route('fidelidad.publico.credencial', $tarjeta->codigo))->assertOk();
        }

        $respuesta->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee($negocio->nombre_comercial)
            ->assertDontSee('5512345678')
            ->assertDontSee('ana@correo.test');
        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));
        $this->assertSame(0, Movimiento::query()->count());
    }

    public function test_doble_clic_o_reintento_con_la_misma_operacion_no_duplica(): void
    {
        $negocio = $this->negocioConFidelidad();
        $campana = $this->campana($negocio);
        $consumidor = $this->consumidor($negocio);
        $empleado = $this->empleado($negocio);
        $uuid = $this->uuid();

        $this->visita($empleado, $consumidor->id, $campana->id, $uuid)->assertOk()->assertJsonPath('repetida', false);
        $this->visita($empleado, $consumidor->id, $campana->id, $uuid)->assertOk()->assertJsonPath('repetida', true);
        $this->visita($empleado, $consumidor->id, $campana->id, strtoupper($uuid))->assertOk()->assertJsonPath('repetida', true);

        $this->assertSame(1, Movimiento::query()->where('tipo', TipoMovimiento::Visita)->count());
        $this->assertSame(1, Participacion::query()->value('visitas_ciclo'));
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'fidelidad.visita_registrada', 'user_id' => $empleado->id]);
    }

    public function test_reglas_de_frecuencia_impiden_visitas_seguidas(): void
    {
        $negocio = $this->negocioConFidelidad();
        $porDia = $this->campana($negocio, ['nombre' => 'Diaria', 'max_visitas_por_dia' => 1]);
        $intervalo = $this->campana($negocio, ['nombre' => 'Intervalo', 'max_visitas_por_dia' => 5, 'intervalo_minimo_minutos' => 60]);
        $consumidor = $this->consumidor($negocio);
        $empleado = $this->empleado($negocio);

        // Dos solicitudes distintas casi simultáneas: la segunda se rechaza.
        $this->visita($empleado, $consumidor->id, $porDia->id)->assertOk();
        $this->visita($empleado, $consumidor->id, $porDia->id)->assertUnprocessable()->assertJsonValidationErrors('visita');

        $this->visita($empleado, $consumidor->id, $intervalo->id)->assertOk();
        $this->visita($empleado, $consumidor->id, $intervalo->id)->assertUnprocessable();

        $this->travel(61)->minutes();
        $this->visita($empleado, $consumidor->id, $intervalo->id)->assertOk();

        $this->assertSame(3, Movimiento::query()->where('tipo', TipoMovimiento::Visita)->count());
    }

    public function test_registros_concurrentes_se_serializan_con_bloqueo_de_fila(): void
    {
        $negocio = $this->negocioConFidelidad();
        $campana = $this->campana($negocio, ['max_visitas_por_dia' => 1]);
        $consumidor = $this->consumidor($negocio);
        $empleado = $this->empleado($negocio);
        $servicio = app(RegistroVisitas::class);

        $servicio->registrarVisita($empleado, $consumidor, $campana, $this->uuid());

        // La segunda transacción espera el bloqueo y ve la visita ya guardada.
        $this->expectException(ValidationException::class);
        $servicio->registrarVisita($empleado, $consumidor->fresh(), $campana, $this->uuid());
    }

    public function test_completar_la_meta_genera_recompensa_y_solo_se_canjea_una_vez(): void
    {
        $negocio = $this->negocioConFidelidad();
        $campana = $this->campana($negocio, ['meta_visitas' => 2]);
        $consumidor = $this->consumidor($negocio);
        $empleado = $this->empleado($negocio);

        $this->visita($empleado, $consumidor->id, $campana->id)->assertOk();
        $this->travel(1)->days();
        $this->visita($empleado, $consumidor->id, $campana->id)->assertOk()->assertJsonFragment(['mensaje' => '¡Meta completada! Recompensa disponible: Un café americano.']);

        $recompensa = Recompensa::query()->firstOrFail();
        $this->assertSame(EstadoRecompensa::Disponible, $recompensa->estado);
        // El ciclo se reinicia (repetir_ciclo).
        $this->assertSame(0, Participacion::query()->value('visitas_ciclo'));

        $canje = $this->uuid();
        $this->actingAs($empleado)->postJson(route('cliente.fidelidad.recompensas.canje', $recompensa), ['operacion_uuid' => $canje])
            ->assertOk()->assertJsonPath('repetida', false);
        // Reintento de la misma operación: idempotente.
        $this->actingAs($empleado)->postJson(route('cliente.fidelidad.recompensas.canje', $recompensa), ['operacion_uuid' => $canje])
            ->assertOk()->assertJsonPath('repetida', true);
        // Nuevo intento de canje: rechazado.
        $this->actingAs($empleado)->postJson(route('cliente.fidelidad.recompensas.canje', $recompensa), ['operacion_uuid' => $this->uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('recompensa');

        $this->assertSame(EstadoRecompensa::Canjeada, $recompensa->fresh()?->estado);
        $this->assertSame($empleado->id, $recompensa->fresh()?->canjeada_por);
    }

    public function test_recompensa_vencida_no_se_canjea(): void
    {
        $negocio = $this->negocioConFidelidad();
        $campana = $this->campana($negocio, ['meta_visitas' => 2, 'vigencia_recompensa_dias' => 5]);
        $consumidor = $this->consumidor($negocio);
        $empleado = $this->empleado($negocio);
        $this->visita($empleado, $consumidor->id, $campana->id);
        $this->travel(1)->days();
        $this->visita($empleado, $consumidor->id, $campana->id);
        $this->travel(6)->days();

        $recompensa = Recompensa::query()->firstOrFail();
        $this->actingAs($empleado)->postJson(route('cliente.fidelidad.recompensas.canje', $recompensa), ['operacion_uuid' => $this->uuid()])
            ->assertUnprocessable();
        $this->assertSame(EstadoRecompensa::Vencida, $recompensa->fresh()?->estado);
    }

    public function test_el_empleado_opera_el_mostrador_pero_no_administra(): void
    {
        $negocio = $this->negocioConFidelidad();
        $campana = $this->campana($negocio);
        $consumidor = $this->consumidor($negocio);
        $empleado = $this->empleado($negocio);

        $this->actingAs($empleado)->get(route('cliente.fidelidad.mostrador'))->assertOk();
        $this->visita($empleado, $consumidor->id, $campana->id)->assertOk();
        $this->actingAs($empleado)->get(route('cliente.fidelidad.campanas.create'))->assertForbidden();
        $this->actingAs($empleado)->post(route('cliente.fidelidad.consumidores.store'), ['nombre' => 'X Y', 'telefono' => '5511112222', 'consentimiento' => true])->assertForbidden();
        $this->actingAs($empleado)->get(route('cliente.fidelidad.personal.index'))->assertForbidden();
        $this->actingAs($empleado)->get(route('cliente.fidelidad.actividad'))->assertForbidden();
        $this->actingAs($empleado)->get(route('inicio'))->assertRedirect(route('cliente.fidelidad.inicio'));
    }

    public function test_los_permisos_no_sustituyen_la_contratacion(): void
    {
        $sinServicio = Negocio::factory()->create();
        $campana = $this->campana($sinServicio);
        $consumidor = $this->consumidor($sinServicio);
        $propietario = $this->propietario($sinServicio);

        $this->assertTrue($propietario->tienePermiso('fidelidad.visitas'));
        $this->visita($propietario, $consumidor->id, $campana->id)->assertForbidden();
        // Sin «Mis servicios» (servicios.ver, no asignado por omisión) vuelve al
        // inicio en lugar de la contratación (ver VisibilidadServiciosTest).
        $this->actingAs($propietario)->get(route('cliente.fidelidad.inicio'))->assertRedirect(route('cliente.inicio'));
        $this->assertSame(0, Movimiento::query()->count());
    }

    public function test_la_contratacion_no_sustituye_los_permisos(): void
    {
        $negocio = $this->negocioConFidelidad();
        $campana = $this->campana($negocio);
        $consumidor = $this->consumidor($negocio);
        $soloConsulta = User::factory()->cliente($negocio)->conRol($this->rolNegocio('Consulta', ['fidelidad.ver']))->create();

        $this->actingAs($soloConsulta)->get(route('cliente.fidelidad.consumidores.index'))->assertOk();
        $this->visita($soloConsulta, $consumidor->id, $campana->id)->assertForbidden();
        $this->assertSame(0, Movimiento::query()->count());
    }

    public function test_ajustes_y_anulaciones_requieren_motivo_y_no_se_repiten(): void
    {
        $negocio = $this->negocioConFidelidad();
        $campana = $this->campana($negocio, ['meta_visitas' => 5]);
        $consumidor = $this->consumidor($negocio);
        $propietario = $this->propietario($negocio);
        $this->visita($propietario, $consumidor->id, $campana->id)->assertOk();
        $visita = Movimiento::query()->firstOrFail();

        $this->actingAs($propietario)->post(route('cliente.fidelidad.movimientos.anulacion', $visita), ['motivo' => ''])->assertSessionHasErrors('motivo');
        $this->actingAs($propietario)->post(route('cliente.fidelidad.movimientos.anulacion', $visita), ['motivo' => 'Registrada por error'])->assertSessionHasNoErrors();
        $this->actingAs($propietario)->post(route('cliente.fidelidad.movimientos.anulacion', $visita), ['motivo' => 'Otra vez'])->assertSessionHasErrors('movimiento');

        $participacion = Participacion::query()->firstOrFail();
        $this->assertSame(0, $participacion->visitas_ciclo);
        $this->assertSame(2, Movimiento::query()->count()); // la visita original no se borra

        // Un ajuste no puede completar la meta (evita recompensas sin visitas reales).
        $this->actingAs($propietario)->post(route('cliente.fidelidad.participaciones.ajuste', $participacion), [
            'cantidad' => 5, 'motivo' => 'Compensación', 'operacion_uuid' => $this->uuid(),
        ])->assertSessionHasErrors('cantidad');

        $this->empleado($negocio);
        $this->actingAs($this->empleado($negocio))->post(route('cliente.fidelidad.participaciones.ajuste', $participacion), [
            'cantidad' => 1, 'motivo' => 'Compensación', 'operacion_uuid' => $this->uuid(),
        ])->assertForbidden();
    }

    public function test_una_credencial_revocada_no_identifica_ni_registra(): void
    {
        $negocio = $this->negocioConFidelidad();
        $campana = $this->campana($negocio);
        $consumidor = $this->consumidor($negocio);
        $tarjeta = $this->tarjeta($consumidor);
        $propietario = $this->propietario($negocio);

        $this->actingAs($propietario)->delete(route('cliente.fidelidad.credenciales.destroy', $tarjeta), ['motivo' => 'Tarjeta perdida'])->assertSessionHasNoErrors();

        $this->actingAs($propietario)->getJson(route('cliente.fidelidad.mostrador.identificar', ['q' => $tarjeta->codigo]))
            ->assertOk()->assertJsonPath('resultados', []);
        $this->visita($propietario, $consumidor->id, $campana->id, null, ['credencial' => $tarjeta->codigo])->assertUnprocessable();
        $this->get(route('fidelidad.publico.credencial', $tarjeta->codigo))->assertNotFound();
    }
}
