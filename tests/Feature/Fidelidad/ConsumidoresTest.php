<?php

namespace Tests\Feature\Fidelidad;

use App\Enums\TipoCredencial;
use App\Models\Fidelidad\Consumidor;
use App\Models\Fidelidad\Credencial;
use App\Models\Fidelidad\Movimiento;
use App\Models\Fidelidad\Participacion;
use App\Models\Rol;
use App\Models\User;
use App\Notifications\Fidelidad\RenovarEnlaceFidelidad;
use App\Notifications\Fidelidad\VerificarCorreoFidelidad;
use App\Services\Fidelidad\GestorCampanas;
use App\Services\Fidelidad\GestorConsumidores;
use App\Services\Fidelidad\RegistroVisitas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\PreparaFidelidad;
use Tests\TestCase;

class ConsumidoresTest extends TestCase
{
    use PreparaFidelidad, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Las páginas públicas cargan recursos de Vite que otros pueden estar
        // recompilando en paralelo.
        $this->withoutVite();
    }

    public function test_alta_exige_nombre_un_contacto_y_consentimiento(): void
    {
        $negocio = $this->negocioConFidelidad();
        $propietario = $this->propietario($negocio);

        $this->actingAs($propietario)->post(route('cliente.fidelidad.consumidores.store'), ['nombre' => 'Luis'])
            ->assertSessionHasErrors(['telefono', 'email', 'consentimiento']);

        $this->actingAs($propietario)->post(route('cliente.fidelidad.consumidores.store'), [
            'nombre' => 'Luis Pérez', 'telefono' => '+52 (55) 1234-5678', 'consentimiento' => true,
        ])->assertSessionHasNoErrors();

        $consumidor = Consumidor::query()->firstOrFail();
        $this->assertSame('5512345678', $consumidor->telefono);
        $this->assertNotNull($consumidor->consentimiento_en);
        $this->assertSame(GestorConsumidores::VERSION_AVISO, $consumidor->consentimiento_version);

        // Solo correo también es válido; duplicado de teléfono se rechaza.
        $this->actingAs($propietario)->post(route('cliente.fidelidad.consumidores.store'), [
            'nombre' => 'Otra', 'telefono' => '5512345678', 'consentimiento' => true,
        ])->assertSessionHasErrors('telefono');
        Notification::fake();
        $this->actingAs($propietario)->post(route('cliente.fidelidad.consumidores.store'), [
            'nombre' => 'Marta Ruiz', 'email' => 'Marta@Correo.test', 'consentimiento' => '1',
        ])->assertSessionHasNoErrors();
        Notification::assertSentTo(Consumidor::query()->where('email', 'marta@correo.test')->firstOrFail(), VerificarCorreoFidelidad::class);
    }

    public function test_los_consumidores_estan_aislados_por_negocio(): void
    {
        $a = $this->negocioConFidelidad();
        $b = $this->negocioConFidelidad();
        $consumidorB = $this->consumidor($b, ['telefono' => '5599990000']);
        $tarjetaB = $this->tarjeta($consumidorB);
        $campanaB = $this->campana($b);
        $usuarioA = $this->propietario($a);

        // El mismo teléfono puede existir en otro negocio.
        $this->consumidor($a, ['telefono' => '5599990000']);

        $this->actingAs($usuarioA)->get(route('cliente.fidelidad.consumidores.show', $consumidorB))->assertNotFound();
        $this->actingAs($usuarioA)->get(route('cliente.fidelidad.campanas.edit', $campanaB))->assertNotFound();
        $this->actingAs($usuarioA)->delete(route('cliente.fidelidad.credenciales.destroy', $tarjetaB), ['motivo' => 'Intento ajeno'])->assertNotFound();
        $this->actingAs($usuarioA)->postJson(route('cliente.fidelidad.visitas.store'), [
            'consumidor_id' => $consumidorB->id, 'campana_id' => $campanaB->id, 'operacion_uuid' => $this->uuid(),
        ])->assertNotFound();
        $this->actingAs($usuarioA)->getJson(route('cliente.fidelidad.mostrador.identificar', ['q' => $tarjetaB->codigo]))
            ->assertOk()->assertJsonPath('resultados', []);
        $this->actingAs($usuarioA)->get(route('cliente.fidelidad.consumidores.index'))
            ->assertInertia(fn (Assert $p) => $p->has('consumidores.data', 1));

        $this->assertTrue($tarjetaB->fresh()?->estaActiva());
        $this->assertSame(0, Movimiento::query()->count());
    }

    public function test_reemplazar_la_tarjeta_conserva_visitas_y_recompensas(): void
    {
        $negocio = $this->negocioConFidelidad();
        $campana = $this->campana($negocio, ['meta_visitas' => 5]);
        $consumidor = $this->consumidor($negocio);
        $tarjeta = $this->tarjeta($consumidor);
        $propietario = $this->propietario($negocio);
        $registro = app(RegistroVisitas::class);

        $registro->registrarVisita($propietario, $consumidor, $campana, $this->uuid(), $tarjeta);
        $this->travel(1)->days();
        $registro->registrarVisita($propietario, $consumidor, $campana, $this->uuid(), $tarjeta);

        $this->actingAs($propietario)->post(route('cliente.fidelidad.credenciales.reemplazo', $tarjeta), [
            'tipo' => 'nfc', 'motivo' => 'El cliente perdió su tarjeta',
        ])->assertSessionHasNoErrors();

        $nueva = Credencial::query()->where('estado', 'activa')->firstOrFail();
        $this->assertNotSame($tarjeta->codigo, $nueva->codigo);
        $this->assertSame('revocada', $tarjeta->fresh()?->estado);
        $this->assertSame(2, Participacion::query()->value('visitas_ciclo'));

        // La tarjeta nueva identifica (y preselecciona) a la misma cuenta.
        $this->actingAs($propietario)->getJson(route('cliente.fidelidad.mostrador.identificar', ['q' => $nueva->urlPublica()]))
            ->assertOk()->assertJsonPath('preseleccion', $consumidor->id);
        $this->actingAs($propietario)->getJson(route('cliente.fidelidad.mostrador.consumidor', $consumidor))
            ->assertOk()->assertJsonPath('consumidor.progreso.0.visitas', 2);
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'fidelidad.credencial_revocada']);
    }

    public function test_las_campanas_conservan_las_condiciones_de_cada_participacion(): void
    {
        $negocio = $this->negocioConFidelidad();
        $campana = $this->campana($negocio, ['meta_visitas' => 10, 'recompensa' => 'Masaje gratis']);
        $antiguo = $this->consumidor($negocio);
        $propietario = $this->propietario($negocio);
        app(RegistroVisitas::class)->registrarVisita($propietario, $antiguo, $campana, $this->uuid());

        app(GestorCampanas::class)->actualizar($campana, [
            'nombre' => 'Café gratis', 'meta_visitas' => 15, 'recompensa' => 'Masaje gratis',
            'inicia_en' => now()->subDay()->toDateString(), 'max_visitas_por_dia' => 1, 'intervalo_minimo_minutos' => 0, 'repetir_ciclo' => true,
        ], $propietario);

        $nuevo = $this->consumidor($negocio);
        app(RegistroVisitas::class)->registrarVisita($propietario, $nuevo, $campana->fresh() ?? $campana, $this->uuid());

        $this->assertSame(2, $campana->fresh()?->version_actual);
        $this->assertSame(10, Participacion::query()->where('consumidor_id', $antiguo->id)->firstOrFail()->version->meta_visitas);
        $this->assertSame(15, Participacion::query()->where('consumidor_id', $nuevo->id)->firstOrFail()->version->meta_visitas);
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'fidelidad.campana_actualizada']);
    }

    public function test_enlace_digital_de_solo_lectura_sin_datos_de_contacto(): void
    {
        $negocio = $this->negocioConFidelidad();
        $this->campana($negocio);
        $consumidor = $this->consumidor($negocio, ['telefono' => '5587654321', 'email' => 'luis@correo.test', 'alias' => 'Luis']);
        $propietario = $this->propietario($negocio);

        $this->actingAs($propietario)->post(route('cliente.fidelidad.consumidores.enlace', $consumidor), ['motivo' => 'Alta en mostrador'])
            ->assertSessionHasErrors('identidad_verificada');
        $this->actingAs($propietario)->post(route('cliente.fidelidad.consumidores.enlace', $consumidor), [
            'motivo' => 'Alta en mostrador', 'identidad_verificada' => true,
        ])->assertSessionHas('enlace_fidelidad');
        $enlace = (string) session('enlace_fidelidad');

        auth()->logout();
        $this->get($enlace)->assertOk()->assertSee('Luis')->assertDontSee('5587654321')->assertDontSee('luis@correo.test');
        $this->assertSame(0, Movimiento::query()->count());
        $this->assertNotSame(basename($enlace), $consumidor->fresh()?->token_acceso_hash, 'Solo se guarda el hash');

        // Renovar el enlace invalida el anterior.
        $this->actingAs($propietario)->post(route('cliente.fidelidad.consumidores.enlace', $consumidor), [
            'motivo' => 'Recuperación presencial', 'identidad_verificada' => true,
        ]);
        auth()->logout();
        $this->get($enlace)->assertNotFound();
        $this->get(route('fidelidad.publico.enlace', str_repeat('a', 48)))->assertNotFound();
    }

    public function test_recuperacion_por_correo_verificado_de_un_solo_uso(): void
    {
        Notification::fake();
        $negocio = $this->negocioConFidelidad();
        $consumidor = $this->consumidor($negocio, ['email' => 'rosa@correo.test']);
        $sinVerificar = $this->consumidor($negocio, ['email' => 'pedro@correo.test']);

        $verificacion = null;
        Notification::assertSentTo($consumidor, VerificarCorreoFidelidad::class, function ($n) use (&$verificacion) {
            $verificacion = $n->url;

            return true;
        });
        $this->get((string) $verificacion)->assertOk()->assertSee('Correo confirmado');
        $this->get((string) $verificacion)->assertNotFound(); // un solo uso
        $this->assertNotNull($consumidor->fresh()?->email_verificado_en);

        // Respuesta neutra, exista o no; solo se envía a correos verificados.
        $this->post(route('fidelidad.publico.recuperar.enviar'), ['email' => 'pedro@correo.test'])->assertRedirect(route('fidelidad.publico.recuperar'));
        $this->post(route('fidelidad.publico.recuperar.enviar'), ['email' => 'nadie@correo.test'])->assertRedirect(route('fidelidad.publico.recuperar'));
        Notification::assertNotSentTo($sinVerificar, RenovarEnlaceFidelidad::class);

        $this->post(route('fidelidad.publico.recuperar.enviar'), ['email' => 'ROSA@correo.test'])->assertRedirect();
        $renovar = null;
        Notification::assertSentTo($consumidor, RenovarEnlaceFidelidad::class, function ($n) use (&$renovar) {
            $renovar = $n->url;

            return true;
        });

        $respuesta = $this->get((string) $renovar)->assertRedirect();
        $this->get((string) $respuesta->headers->get('Location'))->assertOk();
        $this->get((string) $renovar)->assertNotFound(); // ya usado

        $this->travel(2)->hours();
        $this->post(route('fidelidad.publico.recuperar.enviar'), ['email' => 'rosa@correo.test']);
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'fidelidad.enlace_emitido']);
    }

    public function test_fusion_de_cuentas_duplicadas_conserva_el_historial(): void
    {
        $negocio = $this->negocioConFidelidad();
        $campana = $this->campana($negocio, ['meta_visitas' => 10]);
        $original = $this->consumidor($negocio, ['nombre' => 'Ana López']);
        $duplicado = $this->consumidor($negocio, ['nombre' => 'ana  lopez']);
        $propietario = $this->propietario($negocio);
        $registro = app(RegistroVisitas::class);
        $registro->registrarVisita($propietario, $original, $campana, $this->uuid());
        $registro->registrarVisita($propietario, $duplicado, $campana, $this->uuid());
        app(GestorConsumidores::class)->emitirCredencial($duplicado, TipoCredencial::Qr, $propietario);

        $this->actingAs($propietario)->get(route('cliente.fidelidad.consumidores.show', $duplicado))
            ->assertInertia(fn (Assert $p) => $p->where('duplicados.0.id', $original->id));

        $empleado = $this->empleado($negocio);
        $this->actingAs($empleado)->post(route('cliente.fidelidad.consumidores.fusion', $duplicado), ['destino_id' => $original->id, 'motivo' => 'Duplicado'])->assertForbidden();

        $this->actingAs($propietario)->post(route('cliente.fidelidad.consumidores.fusion', $duplicado), [
            'destino_id' => $original->id, 'motivo' => 'Cuenta duplicada en mostrador',
        ])->assertSessionHasNoErrors();

        $this->assertSame(Consumidor::FUSIONADO, $duplicado->fresh()?->estado);
        $this->assertSame(2, Participacion::query()->where('consumidor_id', $original->id)->value('visitas_ciclo'));
        $this->assertSame(1, Credencial::query()->where('consumidor_id', $original->id)->count());
        $this->assertSame(3, Movimiento::query()->count()); // dos visitas + un ajuste de fusión
    }

    public function test_el_propietario_administra_a_su_personal_sin_escalar(): void
    {
        Notification::fake();
        $negocio = $this->negocioConFidelidad();
        $propietario = $this->propietario($negocio);
        $empleadoRol = Rol::porClave('empleado');
        $limitado = User::factory()->cliente($negocio)->conRol($this->rolNegocio('Personal', ['fidelidad.ver', 'fidelidad.personal']))->create();

        $this->actingAs($propietario)->post(route('cliente.fidelidad.personal.store'), [
            'name' => 'Nuevo empleado', 'email' => 'empleado@negocio.test', 'rol_id' => $empleadoRol?->id,
        ])->assertSessionHasNoErrors();
        $nuevo = User::query()->where('email', 'empleado@negocio.test')->firstOrFail();
        $this->assertSame($negocio->id, $nuevo->negocio_id);
        $this->assertTrue($nuevo->esCliente());

        // Sin los permisos del rol Cliente, no puede asignarlo ni tocar al propietario.
        $this->actingAs($limitado)->put(route('cliente.fidelidad.personal.rol', $nuevo), ['rol_id' => Rol::porClave('cliente')?->id])->assertForbidden();
        $this->actingAs($limitado)->patch(route('cliente.fidelidad.personal.estado', $propietario), ['estado' => 'suspendido'])->assertForbidden();

        // Usuarios de otro negocio: 404.
        $ajeno = User::factory()->cliente()->create();
        $this->actingAs($propietario)->patch(route('cliente.fidelidad.personal.estado', $ajeno), ['estado' => 'suspendido'])->assertNotFound();
    }
}
