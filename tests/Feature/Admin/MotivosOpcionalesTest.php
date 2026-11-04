<?php

namespace Tests\Feature\Admin;

use App\Enums\EstadoContratacion;
use App\Enums\EstadoPago;
use App\Models\Contratacion;
use App\Models\Negocio;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\RegistroAuditoria;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaFidelidad;
use Tests\TestCase;

/**
 * Motivos opcionales en operaciones administrativas ordinarias: un motivo
 * vacío se guarda como NULL (nunca texto ficticio) y la auditoría registra
 * de todos modos quién, cuándo, qué y el estado anterior/posterior. Las
 * operaciones sensibles conservan el motivo obligatorio.
 */
class MotivosOpcionalesTest extends TestCase
{
    use PreparaFidelidad, RefreshDatabase;

    public function test_activar_un_servicio_sin_motivo_guarda_nulo_y_audita(): void
    {
        $admin = User::factory()->create();
        $negocio = Negocio::factory()->create();

        $this->actingAs($admin)->post(route('admin.contrataciones.store'), [
            'negocio_id' => $negocio->id, 'servicio' => Servicio::PAGINA_ENLACES, 'origen' => 'manual', 'motivo' => '',
        ])->assertSessionHasNoErrors();

        $contratacion = Contratacion::query()->where('negocio_id', $negocio->id)->firstOrFail();
        $this->assertSame(EstadoContratacion::Activa, $contratacion->estado);
        $this->assertNull($contratacion->motivo);
        $this->assertSame($admin->id, $contratacion->creado_por ?? $admin->id);

        $registro = RegistroAuditoria::query()->where('accion', 'servicio.activado')->where('negocio_id', $negocio->id)->firstOrFail();
        $this->assertSame($admin->id, $registro->user_id);
        $this->assertNotNull($registro->created_at);
        $this->assertSame($contratacion->id, $registro->auditable_id);
        $this->assertStringNotContainsString('Sin motivo', json_encode($registro->valores_nuevos) ?: '');
    }

    public function test_el_pago_fuera_de_linea_tambien_admite_motivo_vacio(): void
    {
        $admin = User::factory()->create();
        $negocio = Negocio::factory()->create();

        $this->actingAs($admin)->post(route('admin.contrataciones.store'), [
            'negocio_id' => $negocio->id, 'servicio' => Servicio::FIDELIDAD, 'origen' => 'fuera_de_linea', 'importe' => '990', 'referencia' => 'SPEI 77',
        ])->assertSessionHasNoErrors();

        $pago = Pago::query()->where('negocio_id', $negocio->id)->firstOrFail();
        $this->assertSame(EstadoPago::Pagado, $pago->estado);
        $this->assertNull($pago->motivo);
    }

    public function test_suspender_y_reactivar_sin_motivo_registra_estado_anterior_y_posterior(): void
    {
        $admin = User::factory()->create();
        $negocio = $this->negocioConFidelidad();
        $contratacion = Contratacion::query()->where('negocio_id', $negocio->id)->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.contrataciones.estado', $contratacion), ['accion' => 'suspender'])
            ->assertSessionHasNoErrors();
        $this->assertSame(EstadoContratacion::Suspendida, $contratacion->fresh()?->estado);
        $this->assertNull($contratacion->fresh()?->motivo);

        $registro = RegistroAuditoria::query()->where('accion', 'servicio.suspendido')->latest('id')->firstOrFail();
        $this->assertSame($admin->id, $registro->user_id);
        $this->assertSame(['estado' => 'activa'], $registro->valores_anteriores);
        $this->assertSame('suspendida', $registro->valores_nuevos['estado'] ?? null);
        $this->assertNull($registro->valores_nuevos['motivo'] ?? null);

        $this->actingAs($admin)->patch(route('admin.contrataciones.estado', $contratacion), ['accion' => 'reactivar', 'motivo' => '   '])
            ->assertSessionHasNoErrors();
        $this->assertSame(EstadoContratacion::Activa, $contratacion->fresh()?->estado);
        $this->assertNull($contratacion->fresh()?->motivo);

        // Con motivo, se conserva tal cual.
        $this->actingAs($admin)->patch(route('admin.contrataciones.estado', $contratacion), ['accion' => 'restringir', 'motivo' => 'Revisión de pago'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Revisión de pago', $contratacion->fresh()?->motivo);
    }

    public function test_marcar_un_pago_revisado_sin_nota(): void
    {
        $admin = User::factory()->create();
        $negocio = Negocio::factory()->create();
        $pago = Pago::query()->create([
            'negocio_id' => $negocio->id, 'servicio_id' => Servicio::porClave(Servicio::FIDELIDAD)->id, 'proveedor' => Pago::PROVEEDOR_OPENPAY,
            'estado' => EstadoPago::Pagado, 'importe_centavos' => 1000, 'moneda' => 'MXN', 'requiere_revision' => true,
        ]);

        $this->actingAs($admin)->patch(route('admin.pagos.revision', $pago), ['nota' => ''])->assertSessionHasNoErrors();

        $this->assertFalse((bool) $pago->fresh()?->requiere_revision);
        $this->assertSame($admin->id, $pago->fresh()?->revisado_por);
        $registro = RegistroAuditoria::query()->where('accion', 'pago.revisado')->firstOrFail();
        $this->assertSame($admin->id, $registro->user_id);
        $this->assertSame(['requiere_revision' => true], $registro->valores_anteriores);
        $this->assertNull($registro->valores_nuevos['nota'] ?? null);
    }

    public function test_las_operaciones_sensibles_conservan_el_motivo_obligatorio(): void
    {
        $admin = User::factory()->create();
        $negocio = Negocio::factory()->create();
        $destino = Negocio::factory()->create();
        $producto = Producto::factory()->for($negocio)->create();

        // Transferir un enlace permanente a otro negocio.
        $this->actingAs($admin)->post(route('admin.productos.transferir', $producto), ['negocio_id' => $destino->id, 'motivo' => ''])
            ->assertSessionHasErrors('motivo');
        $this->assertSame($negocio->id, $producto->fresh()?->negocio_id);

        // Reembolsar dinero.
        $pago = Pago::query()->create([
            'negocio_id' => $negocio->id, 'servicio_id' => Servicio::porClave(Servicio::FIDELIDAD)->id, 'proveedor' => Pago::PROVEEDOR_OPENPAY,
            'estado' => EstadoPago::Pagado, 'importe_centavos' => 1000, 'moneda' => 'MXN',
        ]);
        $this->actingAs($admin)->post(route('admin.pagos.reembolso', $pago), ['tipo' => 'total', 'motivo' => ''])
            ->assertSessionHasErrors('motivo');

        $this->assertSame(EstadoPago::Pagado, $pago->fresh()?->estado);

        // Los ajustes y anulaciones de visitas y la recuperación de
        // credenciales de Fidelidad también exigen motivo (control
        // antifraude): cubierto en Tests\Feature\Fidelidad\AntifraudeTest.
    }
}
