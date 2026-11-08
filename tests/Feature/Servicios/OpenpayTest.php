<?php

namespace Tests\Feature\Servicios;

use App\Enums\EstadoContratacion;
use App\Enums\EstadoIntentoPago;
use App\Enums\EstadoPago;
use App\Enums\OrigenContratacion;
use App\Models\AjustePago;
use App\Models\Contratacion;
use App\Models\IntentoPago;
use App\Models\Negocio;
use App\Models\NotificacionPago;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\RegistroAuditoria;
use App\Models\Rol;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Servicios\AccesoServicios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as PeticionHttp;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Integración con Openpay (formulario de pago alojado) contra una API
 * SIMULADA con estado (Http::fake): no se conecta a Openpay. La prueba
 * contra el sandbox real requiere credenciales de prueba (ver
 * docs/despliegue.md › Openpay).
 */
class OpenpayTest extends TestCase
{
    use RefreshDatabase;

    private const USUARIO = 'openpay-dasti';

    private const CONTRASENA = 'secreto-de-prueba';

    /** @var array<string, array<string, mixed>> Cargos en la API simulada. */
    private array $cargos = [];

    /** @var list<PeticionHttp> */
    private array $peticiones = [];

    private bool $apiCaida = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        // Escenario de estas pruebas: DASTI ya habilitó «Mis servicios» para
        // los clientes (servicios.ver no se asigna por omisión).
        Rol::porClave('cliente')?->givePermissionTo('servicios.ver');

        config([
            'services.openpay.merchant_id' => 'mtestcomercio01',
            'services.openpay.private_key' => 'sk_prueba_simulada',
            'services.openpay.production' => false,
            'services.openpay.webhook_user' => self::USUARIO,
            'services.openpay.webhook_password' => self::CONTRASENA,
        ]);

        foreach ([Servicio::FIDELIDAD, Servicio::PAGINA_ENLACES] as $clave) {
            Servicio::porClave($clave)->forceFill(['precio_centavos' => 149900, 'contratable_en_linea' => true, 'activo' => true])->save();
        }

        Http::fake(function (PeticionHttp $peticion) {
            $this->peticiones[] = $peticion;

            if ($this->apiCaida) {
                return Http::response(['description' => 'Servicio no disponible'], 503);
            }

            $ruta = (string) parse_url($peticion->url(), PHP_URL_PATH);
            $this->assertMatchesRegularExpression('#^https://'.(config('services.openpay.production') ? '' : 'sandbox-').'api\.openpay\.mx/v1/mtestcomercio01/#', $peticion->url());

            if ($peticion->method() === 'POST' && str_ends_with($ruta, '/charges')) {
                $id = 'tr'.substr(md5((string) count($this->cargos)), 0, 18);
                $this->cargos[$id] = [
                    'id' => $id, 'status' => 'charge_pending', 'amount' => $peticion['amount'], 'currency' => $peticion['currency'],
                    'order_id' => $peticion['order_id'], 'method' => 'card', 'error_message' => null, 'authorization' => null,
                    'payment_method' => ['type' => 'redirect', 'url' => "https://sandbox-api.openpay.mx/v1/mtestcomercio01/charges/{$id}/card_capture"],
                ];

                return Http::response($this->cargos[$id]);
            }

            if ($peticion->method() === 'GET' && preg_match('#/charges/([a-z0-9]+)$#', $ruta, $m)) {
                return isset($this->cargos[$m[1]])
                    ? Http::response($this->cargos[$m[1]])
                    : Http::response(['error_code' => 1005, 'description' => 'The requested resource doesn\'t exist'], 404);
            }

            if ($peticion->method() === 'GET' && str_ends_with($ruta, '/charges')) {
                return Http::response(array_values(array_filter($this->cargos, fn ($c) => $c['order_id'] === ($peticion['order_id'] ?? null))));
            }

            if ($peticion->method() === 'POST' && preg_match('#/charges/([a-z0-9]+)/refund$#', $ruta, $m)) {
                $cargo = $this->cargos[$m[1]];
                $monto = $peticion['amount'] ?? $cargo['amount'];
                $this->cargos[$m[1]]['refund'] = ['id' => 'trref1', 'amount' => $monto, 'status' => 'completed'];
                $this->cargos[$m[1]]['status'] = $monto >= $cargo['amount'] ? 'refunded' : 'completed';

                return Http::response($this->cargos[$m[1]]);
            }

            return Http::response(['description' => 'no simulado'], 500);
        });
    }

    // ---- Utilidades ----

    private function propietario(?Negocio $negocio = null): User
    {
        return User::factory()->cliente($negocio ?? Negocio::factory()->create(['telefono' => '5512345678']))->create();
    }

    private function iniciar(User $usuario, string $clave = Servicio::FIDELIDAD): Pago
    {
        $this->actingAs($usuario)->post(route('cliente.servicios.pago', $clave))->assertRedirect();

        return Pago::query()->where('negocio_id', $usuario->negocio_id)->latest('id')->firstOrFail();
    }

    private function intento(Pago $pago): IntentoPago
    {
        return IntentoPago::query()->where('pago_id', $pago->id)->latest('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function cambiarCargo(IntentoPago $intento, array $cambios): void
    {
        $this->cargos[(string) $intento->transaccion_id] = array_replace($this->cargos[(string) $intento->transaccion_id], $cambios);
    }

    /**
     * @param  array<string, mixed>  $carga
     */
    private function notificar(array $carga, ?string $usuario = self::USUARIO, ?string $contrasena = self::CONTRASENA): TestResponse
    {
        $servidor = ['CONTENT_TYPE' => 'application/json'];

        if ($usuario !== null) {
            $servidor['PHP_AUTH_USER'] = $usuario;
            $servidor['PHP_AUTH_PW'] = (string) $contrasena;
        }

        return $this->call('POST', route('openpay.notificaciones'), [], [], [], $servidor, (string) json_encode($carga));
    }

    private function evento(IntentoPago $intento, string $tipo, string $fecha = '2026-11-04T10:00:00-06:00', ?string $estado = null): array
    {
        return [
            'type' => $tipo,
            'event_date' => $fecha,
            // El contenido de la notificación NO es fuente de verdad: aunque
            // diga «completed», se consulta el cargo a la API.
            'transaction' => ['id' => $intento->transaccion_id, 'order_id' => $intento->orden_externa, 'status' => $estado ?? 'completed', 'amount' => 1],
        ];
    }

    private function acceso(): AccesoServicios
    {
        $acceso = app(AccesoServicios::class);
        $acceso->olvidar();

        return $acceso;
    }

    // ---- Orden y cargo ----

    public function test_crea_la_orden_y_el_cargo_con_importe_del_servidor(): void
    {
        $usuario = $this->propietario();

        // El navegador intenta imponer otro precio y otro servicio: se ignoran.
        $respuesta = $this->actingAs($usuario)->post(route('cliente.servicios.pago', Servicio::FIDELIDAD), [
            'importe_centavos' => 100, 'precio' => '1.00', 'servicio_id' => Servicio::paginaEnlaces()->id, 'moneda' => 'USD',
        ]);

        $pago = Pago::query()->firstOrFail();
        $intento = $this->intento($pago);
        $respuesta->assertRedirect((string) $this->cargos[(string) $intento->transaccion_id]['payment_method']['url']);

        $this->assertSame(Servicio::fidelidad()->id, $pago->servicio_id);
        $this->assertSame(149900, $pago->importe_centavos);
        $this->assertSame('MXN', $pago->moneda);
        $this->assertSame(EstadoPago::Pendiente, $pago->estado);
        $this->assertSame(EstadoIntentoPago::Pendiente, $intento->estado);
        $this->assertFalse($intento->livemode);
        $this->assertMatchesRegularExpression('/^DST-[A-Z0-9]{10}$/', (string) $pago->folio);

        $creacion = $this->peticiones[0];
        $this->assertSame(1499.0, $creacion['amount']);
        $this->assertSame('MXN', $creacion['currency']);
        $this->assertFalse($creacion['confirm']);
        $this->assertSame($intento->orden_externa, $creacion['order_id']);
        $this->assertSame(route('cliente.servicios.retorno', ['folio' => $pago->folio]), $creacion['redirect_url']);
        $this->assertSame($usuario->email, $creacion['customer']['email']);
        $this->assertArrayNotHasKey('card_number', $creacion->data());
        // Autenticación HTTP Basic con la llave privada.
        $this->assertSame('Basic '.base64_encode('sk_prueba_simulada:'), $creacion->header('Authorization')[0]);

        $this->assertFalse($this->acceso()->tieneFidelidad($usuario->negocio));
    }

    public function test_repetir_la_compra_reutiliza_el_cargo_abierto_sin_cobrar_dos_veces(): void
    {
        $usuario = $this->propietario();
        $primero = $this->iniciar($usuario);
        $segundo = $this->iniciar($usuario);

        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(1, IntentoPago::query()->count());
        $this->assertCount(1, $this->cargos);
    }

    public function test_sin_credenciales_o_sin_precio_no_se_puede_comprar(): void
    {
        $usuario = $this->propietario();
        config(['services.openpay.private_key' => '']);
        $this->actingAs($usuario)->post(route('cliente.servicios.pago', Servicio::FIDELIDAD))->assertSessionHasErrors('servicio');

        config(['services.openpay.private_key' => 'sk_prueba_simulada']);
        Servicio::fidelidad()->forceFill(['precio_centavos' => null])->save();
        $this->actingAs($usuario)->post(route('cliente.servicios.pago', Servicio::FIDELIDAD))->assertSessionHasErrors('servicio');

        $this->assertSame(0, Pago::query()->count());
        $this->assertSame([], $this->peticiones);
    }

    public function test_un_empleado_sin_permiso_de_contratar_no_puede_pagar(): void
    {
        $negocio = Negocio::factory()->create();
        $empleado = User::factory()->cliente($negocio)->conRol('empleado')->create();

        $this->actingAs($empleado)->post(route('cliente.servicios.pago', Servicio::FIDELIDAD))->assertForbidden();
        $this->assertSame(0, Pago::query()->count());
    }

    public function test_si_openpay_no_responde_la_orden_queda_fallida_con_mensaje_claro(): void
    {
        $this->apiCaida = true;
        $usuario = $this->propietario();

        $this->actingAs($usuario)->post(route('cliente.servicios.pago', Servicio::FIDELIDAD))
            ->assertSessionHasErrors(['servicio' => 'No pudimos conectar con el procesador de pagos. Inténtalo nuevamente en unos minutos.']);
        $this->assertSame(EstadoPago::Fallido, Pago::query()->firstOrFail()->estado);
    }

    // ---- Confirmación ----

    public function test_volver_del_formulario_no_activa_si_openpay_no_confirmo(): void
    {
        $usuario = $this->propietario();
        $pago = $this->iniciar($usuario);

        // El navegador agrega un id y dice «éxito»: no basta.
        $this->actingAs($usuario)->get(route('cliente.servicios.retorno', ['folio' => $pago->folio, 'id' => $this->intento($pago)->transaccion_id, 'status' => 'completed']))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('cliente/servicios/Show')->where('pago.estado', 'pendiente'));

        $this->assertSame(EstadoPago::Pendiente, $pago->fresh()?->estado);
        $this->assertFalse($this->acceso()->tieneFidelidad($usuario->negocio));
        $this->assertSame(0, Contratacion::query()->count());
    }

    public function test_el_retorno_verifica_con_openpay_y_activa_solo_el_servicio_comprado(): void
    {
        $usuario = $this->propietario();
        $pago = $this->iniciar($usuario, Servicio::PAGINA_ENLACES);
        $this->cambiarCargo($this->intento($pago), ['status' => 'completed', 'authorization' => '801585', 'card' => ['brand' => 'visa', 'type' => 'credit', 'card_number' => '411111XXXXXX1111']]);

        $this->actingAs($usuario)->get(route('cliente.servicios.retorno', ['folio' => $pago->folio]))
            ->assertInertia(fn (Assert $p) => $p->where('pago.estado', 'pagado'));

        $this->assertTrue($this->acceso()->tiene($usuario->negocio, Servicio::PAGINA_ENLACES));
        $this->assertFalse($this->acceso()->tieneFidelidad($usuario->negocio), 'Página de enlaces nunca activa Fidelidad.');

        $contratacion = Contratacion::query()->sole();
        $this->assertSame(OrigenContratacion::Openpay, $contratacion->origen);
        $this->assertNull($contratacion->vence_en, 'Pago único sin vencimiento inventado.');

        $intento = $this->intento($pago);
        $this->assertSame('1111', $intento->terminacion_tarjeta);
        $this->assertSame('visa', $intento->marca_tarjeta);
        $this->assertNull($intento->url_pago);
    }

    public function test_pago_aprobado_por_notificacion_activa_una_sola_vez(): void
    {
        $usuario = $this->propietario();
        $pago = $this->iniciar($usuario);
        $intento = $this->intento($pago);
        $this->cambiarCargo($intento, ['status' => 'completed']);

        $this->notificar($this->evento($intento, 'charge.succeeded'))->assertOk()->assertJsonPath('resultado', 'pagado y servicio activado');
        $this->notificar($this->evento($intento, 'charge.succeeded'))->assertOk()->assertJsonPath('resultado', 'duplicado');
        // Reenvío con otra fecha (Openpay reintenta): tampoco duplica.
        $this->notificar($this->evento($intento, 'charge.succeeded', '2026-11-04T10:05:00-06:00'))->assertOk()
            ->assertJsonPath('resultado', 'sin cambios: el pago ya estaba confirmado');
        // El retorno del navegador después de la notificación tampoco.
        $this->actingAs($usuario)->get(route('cliente.servicios.retorno', ['folio' => $pago->folio]))->assertOk();

        $this->assertSame(1, Contratacion::query()->count());
        $this->assertSame(EstadoPago::Pagado, $pago->fresh()?->estado);
        $this->assertTrue($this->acceso()->tieneFidelidad($usuario->negocio));
        $this->assertFalse($this->acceso()->tiene($usuario->negocio, Servicio::PAGINA_ENLACES), 'Fidelidad nunca activa Página de enlaces.');
        $this->assertSame(1, $this->tablaAuditoria('servicio.activado'));
    }

    public function test_la_notificacion_no_es_fuente_de_verdad(): void
    {
        $usuario = $this->propietario();
        $intento = $this->intento($this->iniciar($usuario));

        // La notificación dice «completed» pero la API dice «charge_pending».
        $this->notificar($this->evento($intento, 'charge.succeeded'))->assertOk()->assertJsonPath('resultado', 'pendiente de pago');

        $this->assertSame(0, Contratacion::query()->count());
    }

    public function test_pago_rechazado_y_reintento_con_nuevo_cargo(): void
    {
        $usuario = $this->propietario();
        $pago = $this->iniciar($usuario);
        $intento = $this->intento($pago);
        $this->cambiarCargo($intento, ['status' => 'failed', 'error_message' => 'La tarjeta fue rechazada.']);

        $this->notificar($this->evento($intento, 'charge.failed', estado: 'failed'))->assertOk()->assertJsonPath('resultado', 'fallido');
        $this->assertSame(EstadoPago::Fallido, $pago->fresh()?->estado);
        $this->assertSame(EstadoIntentoPago::Fallido, $intento->fresh()?->estado);
        $this->assertSame(0, Contratacion::query()->count());

        // Nueva orden y nuevo cargo (no se reutiliza el rechazado).
        $nuevo = $this->iniciar($usuario);
        $this->assertNotSame($pago->id, $nuevo->id);
        $this->assertCount(2, $this->cargos);
    }

    public function test_pago_cancelado(): void
    {
        $usuario = $this->propietario();
        $pago = $this->iniciar($usuario);
        $intento = $this->intento($pago);
        $this->cambiarCargo($intento, ['status' => 'cancelled']);

        $this->notificar($this->evento($intento, 'charge.cancelled', estado: 'cancelled'))->assertOk();
        $this->assertSame(EstadoPago::Cancelado, $pago->fresh()?->estado);
        $this->assertSame(0, Contratacion::query()->count());
    }

    // ---- Validación del cargo ----

    public function test_importe_alterado_no_activa_y_queda_en_revision(): void
    {
        $usuario = $this->propietario();
        $pago = $this->iniciar($usuario);
        $intento = $this->intento($pago);
        $this->cambiarCargo($intento, ['status' => 'completed', 'amount' => 1.0]);

        $this->notificar($this->evento($intento, 'charge.succeeded'))->assertOk()->assertJsonPath('resultado', 'rechazado: el importe no coincide');

        $pago->refresh();
        $this->assertTrue($pago->requiere_revision);
        $this->assertSame(EstadoPago::Pendiente, $pago->estado);
        $this->assertSame(0, Contratacion::query()->count());
    }

    public function test_moneda_incorrecta_no_activa(): void
    {
        $usuario = $this->propietario();
        $pago = $this->iniciar($usuario);
        $intento = $this->intento($pago);
        $this->cambiarCargo($intento, ['status' => 'completed', 'currency' => 'USD']);

        $this->notificar($this->evento($intento, 'charge.succeeded'))->assertJsonPath('resultado', 'rechazado: la moneda no coincide');
        $this->assertSame(0, Contratacion::query()->count());
    }

    public function test_referencia_distinta_no_activa(): void
    {
        $usuario = $this->propietario();
        $pago = $this->iniciar($usuario);
        $intento = $this->intento($pago);
        $this->cambiarCargo($intento, ['status' => 'completed', 'order_id' => 'otra-orden']);

        $this->notificar(['type' => 'charge.succeeded', 'event_date' => '2026-11-04T10:00:00-06:00', 'transaction' => ['id' => $intento->transaccion_id]])
            ->assertJsonPath('resultado', 'rechazado: la referencia del cargo no corresponde a la orden');
        $this->assertSame(0, Contratacion::query()->count());
    }

    public function test_referencia_inexistente_se_acepta_y_se_ignora(): void
    {
        $this->notificar(['type' => 'charge.succeeded', 'event_date' => '2026-11-04T10:00:00-06:00', 'transaction' => ['id' => 'trnoexiste', 'order_id' => 'desconocida']])
            ->assertOk()->assertJsonPath('resultado', 'ignorado: referencia inexistente');

        $this->assertSame(0, Contratacion::query()->count());
        $this->assertSame([], $this->peticiones, 'No consulta la API por referencias ajenas.');
    }

    public function test_un_pago_de_pruebas_no_se_confirma_con_la_configuracion_de_produccion(): void
    {
        $usuario = $this->propietario();
        $pago = $this->iniciar($usuario);
        $intento = $this->intento($pago);
        $this->cambiarCargo($intento, ['status' => 'completed']);

        config(['services.openpay.production' => true]);

        $this->notificar($this->evento($intento, 'charge.succeeded'))->assertOk()
            ->assertJsonPath('resultado', 'rechazado: el ambiente del pago (pruebas/producción) no coincide con la configuración actual');
        $this->assertSame(0, Contratacion::query()->count());
        $this->assertTrue($pago->fresh()?->requiere_revision);
    }

    public function test_el_retorno_de_otro_negocio_no_muestra_ni_activa_el_pago(): void
    {
        $usuario = $this->propietario();
        $pago = $this->iniciar($usuario);
        $this->cambiarCargo($this->intento($pago), ['status' => 'completed']);

        $ajeno = $this->propietario();
        $this->actingAs($ajeno)->get(route('cliente.servicios.retorno', ['folio' => $pago->folio]))->assertNotFound();

        $this->assertSame(0, Contratacion::query()->count());
        $this->assertFalse($this->acceso()->tieneFidelidad($ajeno->negocio));
    }

    // ---- Autenticación de notificaciones ----

    public function test_notificaciones_requieren_autenticacion(): void
    {
        $this->notificar(['type' => 'charge.succeeded'], null)->assertUnauthorized();
        $this->notificar(['type' => 'charge.succeeded'], self::USUARIO, 'otra')->assertUnauthorized();

        config(['services.openpay.webhook_password' => '']);
        $this->notificar(['type' => 'charge.succeeded'])->assertStatus(503);

        $this->assertSame(0, NotificacionPago::query()->count());
    }

    public function test_guarda_el_codigo_de_verificacion_cifrado(): void
    {
        $this->notificar(['type' => 'verification', 'event_date' => '2026-11-04T11:04:49-06:00', 'verification_code' => 'UY1qqrxw'])
            ->assertOk()->assertJsonPath('resultado', 'verificación recibida');

        $this->assertSame('UY1qqrxw', NotificacionPago::query()->sole()->codigo_verificacion);
        $this->assertDatabaseMissing('notificaciones_pago', ['codigo_verificacion' => 'UY1qqrxw']);

        $admin = User::factory()->create();
        $this->actingAs($admin)->get(route('admin.pagos.index'))
            ->assertInertia(fn (Assert $p) => $p->where('openpay.codigo_verificacion', 'UY1qqrxw'));
    }

    public function test_si_la_api_falla_la_notificacion_se_reintenta(): void
    {
        $usuario = $this->propietario();
        $intento = $this->intento($this->iniciar($usuario));
        $this->cambiarCargo($intento, ['status' => 'completed']);

        $this->apiCaida = true;
        $this->notificar($this->evento($intento, 'charge.succeeded'))->assertStatus(500);
        $this->assertSame(0, Contratacion::query()->count());

        $this->apiCaida = false;
        $this->notificar($this->evento($intento, 'charge.succeeded'))->assertOk()->assertJsonPath('resultado', 'pagado y servicio activado');
    }

    // ---- Reembolsos y contracargos ----

    private function pagado(?User $usuario = null): array
    {
        $usuario ??= $this->propietario();
        $pago = $this->iniciar($usuario);
        $intento = $this->intento($pago);
        $this->cambiarCargo($intento, ['status' => 'completed']);
        $this->notificar($this->evento($intento, 'charge.succeeded'))->assertOk();

        return [$usuario, $pago->fresh(), $intento->fresh()];
    }

    public function test_reembolso_total_suspende_el_servicio_sin_afectar_redirecciones(): void
    {
        [$usuario, $pago, $intento] = $this->pagado();
        $producto = Producto::factory()->for($usuario->negocio)->create(['url_destino' => 'https://ejemplo.mx']);
        $this->cambiarCargo($intento, ['status' => 'refunded', 'refund' => ['amount' => 1499.0, 'status' => 'completed']]);

        $this->notificar($this->evento($intento, 'charge.refunded', '2026-11-05T10:00:00-06:00'))->assertJsonPath('resultado', 'reembolsado: servicio suspendido');

        $this->assertSame(EstadoPago::Reembolsado, $pago->fresh()?->estado);
        $this->assertSame(149900, $pago->fresh()?->reembolsado_centavos);
        $this->assertSame(EstadoContratacion::Suspendida, Contratacion::query()->sole()->estado);
        $this->assertFalse($this->acceso()->tieneFidelidad($usuario->negocio));
        $this->assertSame(AjustePago::APLICADO, AjustePago::query()->sole()->estado);
        $this->get('/r/'.$producto->codigo)->assertRedirect('https://ejemplo.mx');
    }

    public function test_reembolso_parcial_pasa_a_revision_y_conserva_el_acceso(): void
    {
        [$usuario, $pago, $intento] = $this->pagado();
        $this->cambiarCargo($intento, ['refund' => ['amount' => 500.0, 'status' => 'completed']]);

        $this->notificar($this->evento($intento, 'charge.refunded', '2026-11-05T10:00:00-06:00'))->assertJsonPath('resultado', 'reembolso parcial: revisión de DASTI');

        $pago->refresh();
        $this->assertSame(EstadoPago::ReembolsoParcial, $pago->estado);
        $this->assertSame(50000, $pago->reembolsado_centavos);
        $this->assertTrue($pago->requiere_revision);
        $this->assertTrue($this->acceso()->tieneFidelidad($usuario->negocio));
    }

    public function test_notificacion_atrasada_no_revierte_un_reembolso(): void
    {
        [$usuario, $pago, $intento] = $this->pagado();
        $this->cambiarCargo($intento, ['status' => 'refunded', 'refund' => ['amount' => 1499.0, 'status' => 'completed']]);
        $this->notificar($this->evento($intento, 'charge.refunded', '2026-11-05T10:00:00-06:00'))->assertOk();

        // Llega tarde un «charge.succeeded» antiguo con otra fecha.
        $this->notificar($this->evento($intento, 'charge.succeeded', '2026-11-04T09:59:00-06:00'))->assertOk()
            ->assertJsonPath('resultado', 'sin cambios: reembolso ya registrado');

        $this->assertSame(EstadoPago::Reembolsado, $pago->fresh()?->estado);
        $this->assertSame(1, Contratacion::query()->count());
        $this->assertSame(EstadoContratacion::Suspendida, Contratacion::query()->sole()->estado);
    }

    public function test_pago_reembolsado_antes_de_confirmarse_nunca_activa(): void
    {
        $usuario = $this->propietario();
        $pago = $this->iniciar($usuario);
        $intento = $this->intento($pago);
        $this->cambiarCargo($intento, ['status' => 'refunded']);

        $this->notificar($this->evento($intento, 'charge.succeeded'))->assertOk();
        $this->assertSame(0, Contratacion::query()->count());
        $this->assertSame(EstadoPago::Reembolsado, $pago->fresh()?->estado);
    }

    public function test_contracargo_restringe_y_su_resolucion_a_favor_levanta_la_restriccion(): void
    {
        [$usuario, $pago, $intento] = $this->pagado();
        $this->cambiarCargo($intento, ['status' => 'chargeback_pending']);
        $this->notificar($this->evento($intento, 'chargeback.created', '2026-11-06T10:00:00-06:00'))->assertJsonPath('resultado', 'contracargo: servicio restringido');

        $this->assertSame(EstadoContratacion::Restringida, Contratacion::query()->sole()->estado);
        $this->assertTrue($this->acceso()->puedeConsultarFidelidad($usuario->negocio));
        $this->assertFalse($this->acceso()->puedeOperarFidelidad($usuario->negocio));
        $this->assertTrue($pago->fresh()?->requiere_revision);

        $this->cambiarCargo($intento, ['status' => 'completed']);
        $this->notificar($this->evento($intento, 'chargeback.rejected', '2026-11-20T10:00:00-06:00'))->assertJsonPath('resultado', 'contracargo ganado: restricción levantada');

        $this->assertSame(EstadoContratacion::Activa, Contratacion::query()->sole()->estado);
        $this->assertSame(AjustePago::GANADO, AjustePago::query()->sole()->estado);
    }

    public function test_contracargo_perdido_suspende(): void
    {
        [$usuario, $pago, $intento] = $this->pagado();
        $this->cambiarCargo($intento, ['status' => 'chargeback_pending']);
        $this->notificar($this->evento($intento, 'chargeback.created', '2026-11-06T10:00:00-06:00'))->assertOk();
        $this->cambiarCargo($intento, ['status' => 'chargeback_accepted']);
        $this->notificar($this->evento($intento, 'chargeback.accepted', '2026-11-20T10:00:00-06:00'))->assertJsonPath('resultado', 'contracargo perdido: servicio suspendido');

        $this->assertSame(EstadoContratacion::Suspendida, Contratacion::query()->sole()->estado);
        $this->assertSame(EstadoPago::Reembolsado, $pago->fresh()?->estado);
        $this->assertSame(AjustePago::PERDIDO, AjustePago::query()->sole()->estado);
    }

    public function test_dasti_solicita_un_reembolso_parcial_desde_la_administracion(): void
    {
        [$usuario, $pago] = $this->pagado();
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.pagos.reembolso', $pago), ['tipo' => 'parcial', 'importe' => '300', 'motivo' => 'Ajuste comercial'])
            ->assertSessionHasNoErrors();

        $this->assertSame(30000, $pago->fresh()?->reembolsado_centavos);
        $this->assertSame(EstadoPago::ReembolsoParcial, $pago->fresh()?->estado);
        $this->assertTrue($this->acceso()->tieneFidelidad($usuario->negocio));

        $this->actingAs($admin)->post(route('admin.pagos.reembolso', $pago), ['tipo' => 'parcial', 'importe' => '5000', 'motivo' => 'Excede'])
            ->assertSessionHasErrors('importe');
    }

    // ---- Conciliación programada ----

    public function test_la_conciliacion_confirma_pagos_sin_notificacion_y_caduca_los_abandonados(): void
    {
        $confirmado = $this->iniciar($this->propietario());
        $this->cambiarCargo($this->intento($confirmado), ['status' => 'completed']);
        $abandonado = $this->iniciar($this->propietario());

        $this->travel(25)->hours();
        $this->artisan('dasti:conciliar-pagos')->assertSuccessful();

        $this->assertSame(EstadoPago::Pagado, $confirmado->fresh()?->estado);
        $this->assertSame(EstadoPago::Expirado, $abandonado->fresh()?->estado);
        $this->assertSame(1, Contratacion::query()->count());
    }

    // ---- Activación manual y catálogo ----

    public function test_activacion_manual_de_pagina_de_enlaces_registra_responsable_y_motivo(): void
    {
        $admin = User::factory()->create();
        $negocio = Negocio::factory()->create();

        $this->actingAs($admin)->post(route('admin.contrataciones.store'), [
            'negocio_id' => $negocio->id, 'servicio' => Servicio::PAGINA_ENLACES, 'origen' => 'fuera_de_linea',
            'importe' => '1499', 'referencia' => 'Transferencia 445', 'motivo' => 'Pago recibido por transferencia',
        ])->assertSessionHasNoErrors();

        $contratacion = Contratacion::query()->sole();
        $this->assertSame($admin->id, $contratacion->creado_por);
        $this->assertSame('Pago recibido por transferencia', $contratacion->motivo);
        $this->assertSame('Transferencia 445', $contratacion->pago?->referencia);
        $this->assertTrue($this->acceso()->tiene($negocio, Servicio::PAGINA_ENLACES));
        $this->assertFalse($this->acceso()->tieneFidelidad($negocio));

        $this->actingAs($admin)->post(route('admin.contrataciones.store'), [
            'negocio_id' => $negocio->id, 'servicio' => 'redirecciones', 'origen' => 'manual', 'motivo' => 'No aplica',
        ])->assertSessionHasErrors('servicio');
    }

    public function test_catalogo_del_cliente_muestra_incluidos_disponibles_y_contratados(): void
    {
        $usuario = $this->propietario();
        $this->pagado($usuario);

        $this->actingAs($usuario)->get(route('cliente.servicios.index'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('cliente/servicios/Index')
                ->where('servicios.0.clave', 'redirecciones')->where('servicios.0.incluido', true)
                ->where('servicios.1.clave', 'fidelidad')->where('servicios.1.estado.activo', true)
                ->where('servicios.2.clave', 'pagina_enlaces')->where('servicios.2.estado.activo', false)
                ->where('servicios.2.precio_centavos', 149900)
                ->where('servicios.2.no_disponible', null));
    }

    public function test_dasti_configura_precio_y_beneficios_sin_tocar_codigo(): void
    {
        $admin = User::factory()->create();
        $servicio = Servicio::paginaEnlaces();

        $this->actingAs($admin)->put(route('admin.servicios.update', $servicio), [
            'precio' => '2490.00', 'impuestos_incluidos' => true, 'contratable_en_linea' => true, 'activo' => true,
            'beneficios' => ['Editor visual', 'Dominio propio'],
        ])->assertSessionHasNoErrors();

        $servicio->refresh();
        $this->assertSame(249000, $servicio->precio_centavos);
        $this->assertSame(['Editor visual', 'Dominio propio'], $servicio->beneficios);

        $this->actingAs($admin)->put(route('admin.servicios.update', $servicio), ['precio' => null, 'contratable_en_linea' => true])
            ->assertSessionHasErrors('precio');
    }

    private function tablaAuditoria(string $accion): int
    {
        return RegistroAuditoria::query()->where('accion', $accion)->count();
    }
}
