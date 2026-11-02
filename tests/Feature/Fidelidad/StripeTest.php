<?php

namespace Tests\Feature\Fidelidad;

use App\Enums\EstadoContratacion;
use App\Enums\EstadoPago;
use App\Models\Contratacion;
use App\Models\EventoStripe;
use App\Models\Negocio;
use App\Models\Pago;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Pagos\PasarelaPago;
use App\Services\Servicios\AccesoServicios;
use App\Services\Servicios\GestorContrataciones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\Concerns\PreparaFidelidad;
use Tests\TestCase;

/**
 * Stripe con pasarela SIMULADA para crear sesiones y webhooks firmados con
 * un secreto de prueba (misma verificación que en producción). No contacta
 * a Stripe: la prueba contra la API real requiere claves de prueba.
 */
class StripeTest extends TestCase
{
    use PreparaFidelidad, RefreshDatabase;

    private const SECRETO = 'whsec_prueba_dasti';

    /** @var list<array{pago: int, concepto: string}> */
    private array $sesiones = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.stripe.secret' => 'sk_test_simulado',
            'services.stripe.webhook_secret' => self::SECRETO,
        ]);

        $sesiones = &$this->sesiones;
        $this->app->instance(PasarelaPago::class, new class($sesiones) implements PasarelaPago
        {
            /** @param list<array{pago: int, concepto: string}> $sesiones */
            public function __construct(private array &$sesiones) {}

            public function configurada(): bool
            {
                return (string) config('services.stripe.secret') !== '';
            }

            public function modoReal(): bool
            {
                return false;
            }

            public function crearCheckout(Pago $pago, string $concepto, string $urlExito, string $urlCancelacion): array
            {
                $this->sesiones[] = ['pago' => $pago->id, 'concepto' => $concepto];

                return ['id' => 'cs_test_'.$pago->id, 'url' => 'https://checkout.stripe.com/c/pay/cs_test_'.$pago->id];
            }
        });

        Servicio::fidelidad()->forceFill(['precio_centavos' => 249900, 'vigencia_meses' => 12, 'contratable_en_linea' => true])->save();
    }

    private function iniciar(?Negocio $negocio = null, array $datos = []): Pago
    {
        $negocio ??= Negocio::factory()->create();
        $propietario = $this->propietario($negocio);

        $this->actingAs($propietario)->post(route('cliente.fidelidad.contratacion.checkout'), $datos)
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_'.Pago::query()->latest('id')->value('id'));

        return Pago::query()->latest('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $objeto
     */
    private function evento(string $tipo, array $objeto, ?string $id = null, ?int $creado = null, bool $livemode = false): string
    {
        return (string) json_encode([
            'id' => $id ?? 'evt_'.bin2hex(random_bytes(8)),
            'object' => 'event',
            'type' => $tipo,
            'created' => $creado ?? time(),
            'livemode' => $livemode,
            'data' => ['object' => $objeto],
        ]);
    }

    private function enviar(string $carga, ?string $secreto = null): TestResponse
    {
        $ts = time();
        $firma = hash_hmac('sha256', "{$ts}.{$carga}", $secreto ?? self::SECRETO);

        return $this->call('POST', route('stripe.webhook'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$ts},v1={$firma}",
            'CONTENT_TYPE' => 'application/json',
        ], $carga);
    }

    /**
     * @return array<string, mixed>
     */
    private function sesion(Pago $pago, array $cambios = []): array
    {
        return [
            'id' => $pago->stripe_checkout_session_id,
            'object' => 'checkout.session',
            'client_reference_id' => (string) $pago->id,
            'metadata' => ['pago_id' => (string) $pago->id, 'negocio_id' => (string) $pago->negocio_id, 'servicio_id' => (string) $pago->servicio_id],
            'amount_total' => $pago->importe_centavos,
            'currency' => 'mxn',
            'payment_status' => 'paid',
            'payment_intent' => 'pi_test_'.$pago->id,
            ...$cambios,
        ];
    }

    private function tieneFidelidad(int $negocioId): bool
    {
        app(AccesoServicios::class)->olvidar();

        return app(AccesoServicios::class)->tieneFidelidad(Negocio::query()->findOrFail($negocioId));
    }

    public function test_la_sesion_usa_el_precio_del_servidor_e_ignora_importes_del_navegador(): void
    {
        $pago = $this->iniciar(datos: ['importe' => 1, 'precio_centavos' => 1, 'moneda' => 'usd']);

        $this->assertSame(249900, $pago->importe_centavos);
        $this->assertSame('MXN', $pago->moneda);
        $this->assertSame(EstadoPago::Pendiente, $pago->estado);
        $this->assertSame('cs_test_'.$pago->id, $pago->stripe_checkout_session_id);
        $this->assertCount(1, $this->sesiones);
    }

    public function test_sin_precio_o_sin_credenciales_no_se_puede_contratar(): void
    {
        $negocio = Negocio::factory()->create();
        $propietario = $this->propietario($negocio);

        Servicio::fidelidad()->forceFill(['precio_centavos' => null])->save();
        $this->actingAs($propietario)->post(route('cliente.fidelidad.contratacion.checkout'))->assertSessionHasErrors('servicio');
        $this->actingAs($propietario)->get(route('cliente.fidelidad.contratacion'))
            ->assertInertia(fn (Assert $p) => $p->where('oferta.no_disponible', 'DASTI aún no ha configurado el precio de este servicio.'));

        Servicio::fidelidad()->forceFill(['precio_centavos' => 249900])->save();
        config(['services.stripe.secret' => '']);
        $this->actingAs($propietario)->post(route('cliente.fidelidad.contratacion.checkout'))->assertSessionHasErrors('servicio');
        $this->assertSame(0, Pago::query()->count());
    }

    public function test_un_usuario_sin_permiso_de_contratar_no_inicia_pagos(): void
    {
        $negocio = Negocio::factory()->create();
        $empleado = $this->empleado($negocio);

        $this->actingAs($empleado)->post(route('cliente.fidelidad.contratacion.checkout'))->assertForbidden();
        $this->assertSame(0, Pago::query()->count());
    }

    public function test_pago_aprobado_por_webhook_activa_fidelidad_automaticamente(): void
    {
        $pago = $this->iniciar();

        // Regresar a la página de éxito NO activa nada.
        $this->get(route('cliente.fidelidad.contratacion.exito', ['session_id' => $pago->stripe_checkout_session_id]))
            ->assertOk()->assertInertia(fn (Assert $p) => $p->where('pago.estado', 'pendiente'));
        $this->assertFalse($this->tieneFidelidad($pago->negocio_id));

        $this->enviar($this->evento('checkout.session.completed', $this->sesion($pago)))->assertOk()->assertJsonPath('resultado', 'pagado y servicio activado');

        $pago->refresh();
        $this->assertSame(EstadoPago::Pagado, $pago->estado);
        $this->assertSame('pi_test_'.$pago->id, $pago->stripe_payment_intent_id);
        $this->assertTrue($this->tieneFidelidad($pago->negocio_id));
        $this->assertSame(EstadoContratacion::Activa, Contratacion::query()->firstOrFail()->estado);
        $this->assertNotNull(Contratacion::query()->firstOrFail()->vence_en);
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'pago.confirmado']);
    }

    public function test_firma_invalida_o_ausente_se_rechaza(): void
    {
        $pago = $this->iniciar();
        $carga = $this->evento('checkout.session.completed', $this->sesion($pago));

        $this->enviar($carga, 'whsec_otro')->assertStatus(400);
        $this->call('POST', route('stripe.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json'], $carga)->assertStatus(400);
        $this->assertFalse($this->tieneFidelidad($pago->negocio_id));
        $this->assertSame(0, EventoStripe::query()->count());
    }

    public function test_eventos_duplicados_no_duplican_activaciones(): void
    {
        $pago = $this->iniciar();
        $carga = $this->evento('checkout.session.completed', $this->sesion($pago), 'evt_duplicado');

        $this->enviar($carga)->assertOk();
        $this->enviar($carga)->assertOk()->assertJsonPath('resultado', 'duplicado');
        // Mismo pago con otro id de evento (reenvío de Stripe): tampoco duplica.
        $this->enviar($this->evento('checkout.session.completed', $this->sesion($pago)))->assertOk();

        $this->assertSame(1, Contratacion::query()->count());
        $this->assertSame(2, EventoStripe::query()->count());
    }

    public function test_pago_pendiente_se_activa_al_confirmarse_y_un_fallo_no_activa(): void
    {
        $pago = $this->iniciar();
        $this->enviar($this->evento('checkout.session.completed', $this->sesion($pago, ['payment_status' => 'unpaid'])))->assertOk()
            ->assertJsonPath('resultado', 'pendiente de confirmación');
        $this->assertFalse($this->tieneFidelidad($pago->negocio_id));

        $this->enviar($this->evento('checkout.session.async_payment_succeeded', $this->sesion($pago)))->assertOk();
        $this->assertTrue($this->tieneFidelidad($pago->negocio_id));

        $rechazado = $this->iniciar();
        $this->enviar($this->evento('checkout.session.completed', $this->sesion($rechazado, ['payment_status' => 'unpaid'])));
        $this->enviar($this->evento('checkout.session.async_payment_failed', $this->sesion($rechazado, ['payment_status' => 'unpaid'])))->assertOk();
        $this->assertSame(EstadoPago::Fallido, $rechazado->fresh()?->estado);
        $this->assertFalse($this->tieneFidelidad($rechazado->negocio_id));

        $tarjeta = $this->iniciar();
        $tarjeta->forceFill(['stripe_payment_intent_id' => 'pi_rechazado'])->save();
        $this->enviar($this->evento('payment_intent.payment_failed', ['id' => 'pi_rechazado', 'object' => 'payment_intent']))->assertOk();
        $this->assertSame(EstadoPago::Fallido, $tarjeta->fresh()?->estado);
    }

    public function test_checkout_cancelado_y_sesion_caducada(): void
    {
        $cancelado = $this->iniciar();
        $this->get(route('cliente.fidelidad.contratacion.cancelado', ['pago' => $cancelado->id]))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('pago.estado', 'cancelado'));

        $caducado = $this->iniciar();
        $this->enviar($this->evento('checkout.session.expired', $this->sesion($caducado, ['payment_status' => 'unpaid'])))->assertOk();
        $this->assertSame(EstadoPago::Expirado, $caducado->fresh()?->estado);
        $this->assertSame(0, Contratacion::query()->count());
    }

    public function test_eventos_fuera_de_orden_no_revierten_un_pago_confirmado(): void
    {
        $pago = $this->iniciar();
        $this->enviar($this->evento('checkout.session.completed', $this->sesion($pago), creado: time()))->assertOk();
        // Llega después un «expirado» más antiguo.
        $this->enviar($this->evento('checkout.session.expired', $this->sesion($pago, ['payment_status' => 'unpaid']), creado: time() - 3600))->assertOk();

        $this->assertSame(EstadoPago::Pagado, $pago->fresh()?->estado);
        $this->assertTrue($this->tieneFidelidad($pago->negocio_id));
    }

    public function test_importe_moneda_o_negocio_manipulados_no_activan(): void
    {
        foreach ([
            ['amount_total' => 100],
            ['currency' => 'usd'],
            ['metadata' => ['pago_id' => 'X', 'negocio_id' => '999', 'servicio_id' => '1']],
        ] as $cambio) {
            $pago = $this->iniciar();
            $this->enviar($this->evento('checkout.session.completed', $this->sesion($pago, $cambio)))->assertOk();

            $this->assertSame(EstadoPago::Pendiente, $pago->fresh()?->estado);
            $this->assertTrue((bool) $pago->fresh()?->requiere_revision);
            $this->assertFalse($this->tieneFidelidad($pago->negocio_id));
        }

        // Metadata de OTRO negocio real: tampoco activa a ninguno.
        $victima = Negocio::factory()->create();
        $pago = $this->iniciar();
        $this->enviar($this->evento('checkout.session.completed', $this->sesion($pago, [
            'metadata' => ['pago_id' => (string) $pago->id, 'negocio_id' => (string) $victima->id, 'servicio_id' => (string) $pago->servicio_id],
        ])))->assertOk();
        $this->assertFalse($this->tieneFidelidad($victima->id));
        $this->assertFalse($this->tieneFidelidad($pago->negocio_id));
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'pago.rechazado_validacion']);
    }

    public function test_eventos_de_otro_entorno_se_ignoran(): void
    {
        $pago = $this->iniciar();
        $this->enviar($this->evento('checkout.session.completed', $this->sesion($pago), livemode: true))->assertOk()
            ->assertJsonPath('resultado', 'ignorado: entorno distinto (prueba/producción)');
        $this->assertFalse($this->tieneFidelidad($pago->negocio_id));
    }

    public function test_reembolso_total_suspende_y_parcial_solo_notifica(): void
    {
        $total = $this->iniciar();
        $this->enviar($this->evento('checkout.session.completed', $this->sesion($total)));
        $this->enviar($this->evento('charge.refunded', ['id' => 'ch_1', 'object' => 'charge', 'payment_intent' => 'pi_test_'.$total->id, 'amount_refunded' => 249900]))
            ->assertOk()->assertJsonPath('resultado', 'reembolsado: servicio suspendido');
        $this->assertSame(EstadoPago::Reembolsado, $total->fresh()?->estado);
        $this->assertFalse($this->tieneFidelidad($total->negocio_id));
        $this->assertSame(EstadoContratacion::Suspendida, Contratacion::query()->where('negocio_id', $total->negocio_id)->firstOrFail()->estado);

        $parcial = $this->iniciar();
        $this->enviar($this->evento('checkout.session.completed', $this->sesion($parcial)));
        $this->enviar($this->evento('charge.refunded', ['id' => 'ch_2', 'object' => 'charge', 'payment_intent' => 'pi_test_'.$parcial->id, 'amount_refunded' => 50000]))->assertOk();
        $this->assertSame(EstadoPago::ReembolsoParcial, $parcial->fresh()?->estado);
        $this->assertTrue((bool) $parcial->fresh()?->requiere_revision);
        $this->assertTrue($this->tieneFidelidad($parcial->negocio_id));
    }

    public function test_reembolso_total_con_otro_derecho_vigente_no_suspende(): void
    {
        $negocio = $this->negocioConFidelidad(); // activación manual vigente
        $pago = $this->iniciar($negocio);
        $this->enviar($this->evento('checkout.session.completed', $this->sesion($pago)));
        $this->enviar($this->evento('charge.refunded', ['id' => 'ch_3', 'object' => 'charge', 'payment_intent' => 'pi_test_'.$pago->id, 'amount_refunded' => 249900]))
            ->assertOk()->assertJsonPath('resultado', 'reembolsado: periodo cancelado, otro derecho vigente');

        $this->assertTrue($this->tieneFidelidad($negocio->id));
    }

    public function test_disputa_restringe_y_su_resolucion_decide(): void
    {
        $ganada = $this->iniciar();
        $this->enviar($this->evento('checkout.session.completed', $this->sesion($ganada)));
        $this->enviar($this->evento('charge.dispute.created', ['id' => 'dp_1', 'object' => 'dispute', 'payment_intent' => 'pi_test_'.$ganada->id]))->assertOk();

        app(AccesoServicios::class)->olvidar();
        $estado = app(AccesoServicios::class)->fidelidad(Negocio::query()->findOrFail($ganada->negocio_id));
        $this->assertFalse($estado['activo']);
        $this->assertTrue($estado['solo_lectura']);

        $this->enviar($this->evento('charge.dispute.closed', ['id' => 'dp_1', 'object' => 'dispute', 'payment_intent' => 'pi_test_'.$ganada->id, 'status' => 'won']))->assertOk();
        $this->assertTrue($this->tieneFidelidad($ganada->negocio_id));

        $perdida = $this->iniciar();
        $this->enviar($this->evento('checkout.session.completed', $this->sesion($perdida)));
        $this->enviar($this->evento('charge.dispute.created', ['id' => 'dp_2', 'object' => 'dispute', 'payment_intent' => 'pi_test_'.$perdida->id]));
        $this->enviar($this->evento('charge.dispute.closed', ['id' => 'dp_2', 'object' => 'dispute', 'payment_intent' => 'pi_test_'.$perdida->id, 'status' => 'lost']))->assertOk();
        $this->assertSame(EstadoContratacion::Suspendida, Contratacion::query()->where('negocio_id', $perdida->negocio_id)->firstOrFail()->estado);
    }

    public function test_dasti_puede_mantener_la_restriccion_aunque_se_gane_la_disputa(): void
    {
        $admin = User::factory()->create();
        $pago = $this->iniciar();
        $this->enviar($this->evento('checkout.session.completed', $this->sesion($pago)));
        $this->enviar($this->evento('charge.dispute.created', ['id' => 'dp_3', 'object' => 'dispute', 'payment_intent' => 'pi_test_'.$pago->id]));
        $contratacion = Contratacion::query()->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.contrataciones.estado', $contratacion), ['accion' => 'restringir', 'motivo' => 'Revisión antifraude'])->assertSessionHasNoErrors();
        $this->enviar($this->evento('charge.dispute.closed', ['id' => 'dp_3', 'object' => 'dispute', 'payment_intent' => 'pi_test_'.$pago->id, 'status' => 'won']))
            ->assertJsonPath('resultado', 'disputa ganada: DASTI mantiene la restricción');
        $this->assertSame(EstadoContratacion::Restringida, $contratacion->fresh()?->estado);
    }

    public function test_cierre_de_disputa_recibido_antes_que_su_apertura(): void
    {
        $pago = $this->iniciar();
        $this->enviar($this->evento('checkout.session.completed', $this->sesion($pago)));
        $this->enviar($this->evento('charge.dispute.closed', ['id' => 'dp_4', 'object' => 'dispute', 'payment_intent' => 'pi_test_'.$pago->id, 'status' => 'won']))->assertOk();
        $this->enviar($this->evento('charge.dispute.created', ['id' => 'dp_4', 'object' => 'dispute', 'payment_intent' => 'pi_test_'.$pago->id]))
            ->assertOk()->assertJsonPath('resultado', 'ignorado: la disputa ya estaba cerrada');

        $this->assertTrue($this->tieneFidelidad($pago->negocio_id));
    }

    public function test_un_error_de_procesamiento_permite_el_reintento(): void
    {
        $pago = $this->iniciar();
        $carga = $this->evento('checkout.session.completed', $this->sesion($pago), 'evt_reintento');

        $real = $this->app->make(GestorContrataciones::class);
        $this->app->instance(GestorContrataciones::class, \Mockery::mock(GestorContrataciones::class, function ($m) {
            $m->shouldReceive('activar')->andThrow(new RuntimeException('Base de datos no disponible'));
        }));

        $this->enviar($carga)->assertStatus(500);
        $this->assertSame(EstadoPago::Pendiente, $pago->fresh()?->estado);
        $this->assertNull(EventoStripe::query()->where('stripe_event_id', 'evt_reintento')->value('procesado_en'));

        $this->app->instance(GestorContrataciones::class, $real);
        $this->enviar($carga)->assertOk()->assertJsonPath('resultado', 'pagado y servicio activado');
        $this->assertTrue($this->tieneFidelidad($pago->negocio_id));
    }

    public function test_el_webhook_sin_secreto_configurado_no_procesa(): void
    {
        config(['services.stripe.webhook_secret' => '']);
        $this->call('POST', route('stripe.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}')->assertStatus(503);
    }
}
