<?php

namespace Tests\Feature\Fidelidad;

use App\Enums\EstadoContratacion;
use App\Enums\EstadoPago;
use App\Models\Contratacion;
use App\Models\Fidelidad\Consumidor;
use App\Models\Negocio;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Servicios\AccesoServicios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\PreparaFidelidad;
use Tests\TestCase;

class ContratacionesTest extends TestCase
{
    use PreparaFidelidad, RefreshDatabase;

    public function test_dasti_activa_manualmente_y_registra_pago_fuera_de_linea(): void
    {
        $admin = User::factory()->create();
        $negocio = Negocio::factory()->create();

        $this->actingAs($admin)->post(route('admin.contrataciones.store'), [
            'negocio_id' => $negocio->id, 'servicio' => 'fidelidad', 'origen' => 'manual', 'motivo' => 'Cortesía comercial',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(app(AccesoServicios::class)->tieneFidelidad($negocio));

        $otro = Negocio::factory()->create();
        $this->actingAs($admin)->post(route('admin.contrataciones.store'), [
            'negocio_id' => $otro->id, 'servicio' => 'fidelidad', 'origen' => 'fuera_de_linea', 'motivo' => 'Transferencia', 'importe' => '1500.50', 'referencia' => 'SPEI 123',
        ])->assertSessionHasNoErrors();

        $pago = Pago::query()->where('negocio_id', $otro->id)->firstOrFail();
        $this->assertSame(150050, $pago->importe_centavos);
        $this->assertSame(EstadoPago::Pagado, $pago->estado);
        $this->assertNotNull($pago->contratacion_id);
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'servicio.activado', 'negocio_id' => $otro->id]);
    }

    public function test_suspender_y_reactivar_conserva_datos_y_no_afecta_redirecciones(): void
    {
        $admin = User::factory()->create();
        $negocio = $this->negocioConFidelidad();
        $consumidor = $this->consumidor($negocio);
        $producto = Producto::factory()->for($negocio)->create(['url_destino' => 'https://ejemplo.mx']);
        $contratacion = Contratacion::query()->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.contrataciones.estado', $contratacion), ['accion' => 'suspender', 'motivo' => 'Falta de pago'])->assertSessionHasNoErrors();
        app(AccesoServicios::class)->olvidar();

        $propietario = $this->propietario($negocio);
        $this->actingAs($propietario)->get(route('cliente.fidelidad.consumidores.index'))->assertRedirect(route('cliente.servicios.show', 'fidelidad'));
        $this->assertNotNull(Consumidor::query()->find($consumidor->id));
        $this->get('/r/'.$producto->codigo)->assertRedirect('https://ejemplo.mx');
        $this->assertTrue($negocio->fresh()?->estaActivo());

        $this->actingAs($admin)->patch(route('admin.contrataciones.estado', $contratacion), ['accion' => 'reactivar', 'motivo' => 'Pago aclarado'])->assertSessionHasNoErrors();
        $this->actingAs($propietario)->get(route('cliente.fidelidad.consumidores.index'))->assertOk();
    }

    public function test_al_vencer_queda_en_solo_lectura_sin_perder_datos(): void
    {
        $servicio = Servicio::fidelidad();
        $servicio->forceFill(['vigencia_meses' => 1])->save();
        $negocio = $this->negocioConFidelidad();
        $campana = $this->campana($negocio);
        $consumidor = $this->consumidor($negocio);
        $propietario = $this->propietario($negocio);

        $this->travel(32)->days();
        $this->artisan('dasti:vencer-contrataciones')->assertSuccessful();
        $this->assertSame(EstadoContratacion::Vencida, Contratacion::query()->firstOrFail()->estado);

        $this->actingAs($propietario)->get(route('cliente.fidelidad.consumidores.show', $consumidor))
            ->assertOk()->assertInertia(fn (Assert $p) => $p->where('servicio.solo_lectura', true)->where('puede.visitas', false));
        $this->actingAs($propietario)->postJson(route('cliente.fidelidad.visitas.store'), [
            'consumidor_id' => $consumidor->id, 'campana_id' => $campana->id, 'operacion_uuid' => $this->uuid(),
        ])->assertForbidden();
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'servicio.vencido']);
    }

    public function test_un_pago_renovado_empieza_al_terminar_el_periodo_vigente(): void
    {
        Servicio::fidelidad()->forceFill(['vigencia_meses' => 12])->save();
        $negocio = $this->negocioConFidelidad();
        $primero = Contratacion::query()->firstOrFail();
        $this->negocioConFidelidad($negocio);

        $segundo = Contratacion::query()->latest('id')->firstOrFail();
        $this->assertEquals($primero->vence_en?->toDateString(), $segundo->inicia_en?->toDateString());
    }

    public function test_permisos_del_panel_de_servicios(): void
    {
        $lectura = new Rol;
        $lectura->forceFill(['name' => 'Ve servicios', 'guard_name' => 'web', 'ambito' => 'plataforma'])->save();
        $lectura->syncPermissions(['servicios.ver', 'pagos.ver']);
        $soloVer = User::factory()->conRol($lectura)->create();
        $negocio = Negocio::factory()->create();

        $this->actingAs($soloVer)->get(route('admin.servicios.index'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('admin/servicios/Index')->where('puede_gestionar', false));
        $this->actingAs($soloVer)->get(route('admin.pagos.index'))->assertOk();
        $this->actingAs($soloVer)->post(route('admin.contrataciones.store'), ['negocio_id' => $negocio->id, 'origen' => 'manual', 'motivo' => 'Intento'])->assertForbidden();
        $this->actingAs($soloVer)->put(route('admin.servicios.update', Servicio::fidelidad()), ['precio' => 10])->assertForbidden();

        $cliente = User::factory()->cliente()->create();
        $this->actingAs($cliente)->get(route('admin.servicios.index'))->assertForbidden();
    }

    public function test_dasti_configura_precio_y_vigencia(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->put(route('admin.servicios.update', Servicio::fidelidad()), [
            'precio' => '2499.00', 'vigencia_meses' => 12, 'contratable_en_linea' => true, 'activo' => true,
        ])->assertSessionHasNoErrors();

        $servicio = Servicio::fidelidad();
        $this->assertSame(249900, $servicio->precio_centavos);
        $this->assertSame(12, $servicio->vigencia_meses);
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'servicio.configurado']);
    }

    public function test_el_personal_de_dasti_no_accede_a_datos_de_consumidores(): void
    {
        $super = User::factory()->superadministrador()->create();
        $negocio = $this->negocioConFidelidad();
        $consumidor = $this->consumidor($negocio);

        $this->assertFalse($super->tienePermiso('fidelidad.ver'));
        $this->actingAs($super)->get(route('cliente.fidelidad.consumidores.show', $consumidor))->assertForbidden();
    }
}
