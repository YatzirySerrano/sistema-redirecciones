<?php

namespace Tests\Feature\Paginas;

use App\Enums\EstadoDominio;
use App\Models\Paginas\DominioPagina;
use App\Models\Paginas\PaginaEnlaces;
use App\Models\Producto;
use App\Services\Paginas\Dominios\GestorDominios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\PreparaPaginas;
use Tests\TestCase;

class DominiosTest extends TestCase
{
    use PreparaPaginas, RefreshDatabase;

    private const IP = '203.0.113.10';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        config(['dasti.paginas.dominios.ips_ingreso' => [self::IP], 'dasti.paginas.dominios.cname' => 'paginas.dasti.cloud']);
        $this->falsearDns();
    }

    private function gestor(): GestorDominios
    {
        return app(GestorDominios::class);
    }

    private function apuntar(DominioPagina $dominio, string $cname = 'paginas.dasti.cloud', ?string $token = null): void
    {
        $this->dns->txt[$dominio->nombreRegistroTxt()] = [$token ?? $dominio->token];
        $this->dns->cname[$dominio->host] = [$cname];
        $this->dns->ips[$dominio->host] = [self::IP];
    }

    /**
     * @return array{0: PaginaEnlaces, 1: DominioPagina}
     */
    private function dominioActivo(string $host = 'enlaces.cafe-aurora.mx'): array
    {
        $negocio = $this->negocioConPaginas();
        $pagina = $this->publicar($negocio, $this->documento([$this->enlace('Menú', 'https://cafe-aurora.mx/menu')]));
        $dominio = $this->gestor()->registrar($pagina, $host, $this->propietario($negocio));
        $this->apuntar($dominio);

        return [$pagina, $this->gestor()->verificar($dominio)];
    }

    public function test_normalizacion_y_hosts_rechazados(): void
    {
        $this->assertSame('enlaces.cafe-aurora.mx', $this->gestor()->normalizar('HTTPS://Enlaces.Cafe-Aurora.MX/menu?x=1'));
        $this->assertSame('xn--caf-dma.mx', $this->gestor()->normalizar('café.mx'));

        foreach (['redireccion.test', 'paginas.redireccion.test', 'dasti.cloud', 'cliente.dasti.cloud', 'paginas.dasti.cloud', '203.0.113.10', '2130706433',
            'localhost', 'mi-negocio.local', 'servidor.internal', 'cafe.mx:8080', '*.cafe.mx', 'sin-extension', 'cafe.123', ''] as $malo) {
            try {
                $this->gestor()->normalizar($malo);
                $this->fail("Debió rechazarse «{$malo}»");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('host', $e->errors());
            }
        }
    }

    public function test_ciclo_de_vida_pendiente_verificado_activo(): void
    {
        $negocio = $this->negocioConPaginas();
        $pagina = $this->publicar($negocio, $this->documento([$this->enlace()]));
        $propietario = $this->propietario($negocio);
        $dominio = $this->gestor()->registrar($pagina, 'Enlaces.Cafe-Aurora.mx', $propietario);

        $this->assertSame(EstadoDominio::Pendiente, $dominio->estado);
        $this->assertStringStartsWith('dasti-', $dominio->token);
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'pagina.dominio_agregado', 'negocio_id' => $negocio->id]);

        // Sin registros DNS: sigue pendiente con un motivo claro.
        $dominio = $this->gestor()->verificar($dominio);
        $this->assertSame(EstadoDominio::Pendiente, $dominio->estado);
        $this->assertStringContainsString('TXT', (string) $dominio->ultimo_error);
        $this->assertNull($dominio->host_verificado);
        $this->get('https://enlaces.cafe-aurora.mx/')->assertNotFound();

        // DNS correcto pero HTTPS aún sin certificado: nunca «activo».
        $this->apuntar($dominio);
        $this->https->error = 'El certificado HTTPS aún no está listo.';
        $dominio = $this->gestor()->verificar($dominio);
        $this->assertSame(EstadoDominio::DnsVerificado, $dominio->estado);
        $this->assertNull($dominio->https_verificado_en);
        $this->assertSame([['host' => 'enlaces.cafe-aurora.mx', 'ip' => self::IP, 'token' => $dominio->token]], $this->https->llamadas, 'La comprobación HTTPS se fuerza a la IP de DASTI.');
        $this->assertSame('https://redireccion.test/l/'.$pagina->slug, $pagina->fresh()?->urlCanonica());

        // HTTPS correcto: activo y canónico.
        $this->https->error = null;
        $dominio = $this->gestor()->verificar($dominio);
        $this->assertSame(EstadoDominio::Activo, $dominio->estado);
        $this->assertNotNull($dominio->https_verificado_en);
        $this->assertTrue($dominio->es_principal);
        $this->assertSame('https://enlaces.cafe-aurora.mx/', $pagina->fresh()?->urlCanonica());
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'pagina.dominio_verificado', 'negocio_id' => $negocio->id]);

        $html = (string) $this->get('https://enlaces.cafe-aurora.mx/')->assertOk()->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="https://enlaces.cafe-aurora.mx/">', $html);
        // La dirección original de DASTI sigue funcionando como respaldo.
        $this->get('https://redireccion.test/l/'.$pagina->slug)->assertOk();
    }

    public function test_destino_incorrecto_y_dominio_que_deja_de_apuntar(): void
    {
        $negocio = $this->negocioConPaginas();
        $pagina = $this->publicar($negocio, $this->documento([$this->enlace()]));
        $dominio = $this->gestor()->registrar($pagina, 'cafe-aurora.mx', $this->propietario($negocio));
        $this->dns->txt[$dominio->nombreRegistroTxt()] = [$dominio->token];
        $this->dns->ips['cafe-aurora.mx'] = ['198.51.100.7'];

        $dominio = $this->gestor()->verificar($dominio);
        $this->assertSame(EstadoDominio::Pendiente, $dominio->estado);
        $this->assertStringContainsString('198.51.100.7', (string) $dominio->ultimo_error);
        $this->assertSame([], $this->https->llamadas, 'Nunca se conecta a una IP que no es de DASTI.');

        // Una IP de DASTI y otra ajena tampoco cuenta como «apunta a DASTI».
        $this->dns->ips['cafe-aurora.mx'] = [self::IP, '198.51.100.7'];
        $this->assertSame(EstadoDominio::Pendiente, $this->gestor()->verificar($dominio)->estado);

        // Pendiente demasiado tiempo: error.
        $dominio->forceFill(['created_at' => now()->subDays(8)])->save();
        $this->assertSame(EstadoDominio::Error, $this->gestor()->verificar($dominio)->estado);

        // Activo que deja de apuntar: tolera fallos temporales y luego error;
        // la página y su configuración se conservan.
        [$pagina2, $activo] = $this->dominioActivo('links.panaderia.mx');
        $this->dns->cname['links.panaderia.mx'] = ['otro-servicio.com'];
        $this->dns->ips['links.panaderia.mx'] = ['198.51.100.9'];
        $this->assertSame(EstadoDominio::Activo, $this->gestor()->verificar($activo)->estado);
        $this->assertSame(EstadoDominio::Activo, $this->gestor()->verificar($activo)->estado);
        $activo = $this->gestor()->verificar($activo);
        $this->assertSame(EstadoDominio::Error, $activo->estado);
        $this->assertNull($activo->host_verificado);
        $this->get('https://links.panaderia.mx/')->assertNotFound();
        $this->get('https://redireccion.test/l/'.$pagina2->slug)->assertOk();
    }

    public function test_duplicados_y_secuestro_por_otro_negocio(): void
    {
        [$pagina, $dominio] = $this->dominioActivo();

        try {
            $this->gestor()->registrar($pagina, 'enlaces.cafe-aurora.mx', $this->propietario($pagina->negocio));
            $this->fail('Duplicado en la misma página.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Ya agregaste', $e->errors()['host'][0]);
        }

        $intruso = $this->negocioConPaginas();
        $paginaIntruso = $this->publicar($intruso, $this->documento([$this->enlace()]));

        try {
            $this->gestor()->registrar($paginaIntruso, 'enlaces.cafe-aurora.mx', $this->propietario($intruso));
            $this->fail('Un dominio verificado por otro negocio no se puede registrar.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('otra cuenta', $e->errors()['host'][0]);
        }

        // Dominio aún pendiente en un negocio: otro negocio puede
        // registrarlo, pero solo verifica quien controla el DNS (token).
        $legitimo = $this->negocioConPaginas();
        $paginaLegitima = $this->publicar($legitimo, $this->documento([$this->enlace()]));
        $suyo = $this->gestor()->registrar($paginaLegitima, 'tienda-legitima.mx', $this->propietario($legitimo));
        $ajeno = $this->gestor()->registrar($paginaIntruso, 'tienda-legitima.mx', $this->propietario($intruso));
        $this->dns->txt[$suyo->nombreRegistroTxt()] = [$suyo->token];
        $this->dns->ips['tienda-legitima.mx'] = [self::IP];

        $this->assertNotSame(EstadoDominio::Activo, $this->gestor()->verificar($ajeno)->estado);
        $this->assertSame(EstadoDominio::Activo, $this->gestor()->verificar($suyo)->estado);

        // Aunque el intruso lograra publicar su token, el host ya pertenece
        // al negocio que lo verificó primero.
        $this->dns->txt[$ajeno->nombreRegistroTxt()] = [$suyo->token, $ajeno->token];
        $ajeno = $this->gestor()->verificar($ajeno->fresh() ?? $ajeno);
        $this->assertNotSame(EstadoDominio::Activo, $ajeno->estado);
        $this->assertStringContainsString('otra cuenta', (string) $ajeno->ultimo_error);
        $this->assertStringContainsString('Menú', (string) $this->get('https://enlaces.cafe-aurora.mx/')->getContent());
    }

    public function test_desvincular_cambiar_y_dominio_principal(): void
    {
        [$pagina, $dominio] = $this->dominioActivo();
        $propietario = $this->propietario($pagina->negocio);

        // Segundo dominio activo: redirige (301) al principal.
        $www = $this->gestor()->registrar($pagina, 'www.cafe-aurora.mx', $propietario);
        $this->apuntar($www);
        $www = $this->gestor()->verificar($www);
        $this->assertSame(EstadoDominio::Activo, $www->estado);
        $this->assertFalse($www->es_principal);
        $this->get('https://www.cafe-aurora.mx/')->assertStatus(301)->assertRedirect('https://enlaces.cafe-aurora.mx/');

        $this->gestor()->hacerPrincipal($www, $propietario);
        $this->get('https://enlaces.cafe-aurora.mx/')->assertRedirect('https://www.cafe-aurora.mx/');
        $this->get('https://www.cafe-aurora.mx/')->assertOk();

        // Cambio de dominio sin interrupción: el anterior se retira cuando
        // el nuevo queda activo.
        $nuevo = $this->gestor()->registrar($pagina, 'cafeaurora.com.mx', $propietario, $www);
        $this->assertNotNull(DominioPagina::query()->find($www->id));
        $this->dns->txt[$nuevo->nombreRegistroTxt()] = [$nuevo->token];
        $this->dns->ips['cafeaurora.com.mx'] = [self::IP];
        $nuevo = $this->gestor()->verificar($nuevo);
        $this->assertSame(EstadoDominio::Activo, $nuevo->estado);
        $this->assertNull(DominioPagina::query()->find($www->id));
        $this->assertTrue($nuevo->es_principal);
        $this->get('https://www.cafe-aurora.mx/')->assertNotFound();

        // Desvincular: deja de responder y queda en la auditoría.
        $this->gestor()->eliminar($nuevo, $propietario);
        $this->get('https://cafeaurora.com.mx/')->assertNotFound();
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'pagina.dominio_eliminado', 'negocio_id' => $pagina->negocio_id]);
        $this->assertTrue((bool) DominioPagina::query()->find($dominio->id)?->es_principal);
    }

    public function test_un_dominio_propio_solo_sirve_la_pagina_de_su_negocio(): void
    {
        [$pagina, $dominio] = $this->dominioActivo();
        $imagen = $this->imagen($pagina->negocio);
        $otro = $this->negocioConPaginas();
        $paginaOtro = $this->publicar($otro, $this->documento([$this->enlace('Secreto')]));
        $imagenAjena = $this->imagen($otro);
        $producto = Producto::factory()->for($pagina->negocio)->create(['url_destino' => 'https://instagram.com/cafe']);
        $host = 'https://enlaces.cafe-aurora.mx';

        $respuesta = $this->get($host.'/')->assertOk();
        $this->assertEmpty($respuesta->headers->getCookies(), 'Sin sesión ni cookies en el dominio propio.');

        foreach (['/login', '/admin', '/admin/paginas', '/panel', '/panel/paginas', '/inicio', '/settings/profile', '/r/'.$producto->codigo,
            '/l/'.$paginaOtro->slug, '/l/'.$pagina->slug, '/webhooks/openpay', '/up', '/destinos/validacion', '/medios/'.$imagenAjena->uuid.'.'.$imagenAjena->extension] as $ruta) {
            $this->get($host.$ruta)->assertNotFound()->assertDontSee('Secreto');
        }

        $this->post($host.'/login', ['email' => 'a@b.mx', 'password' => 'x'])->assertNotFound();
        $this->get($host.'/medios/'.$imagen->uuid.'.'.$imagen->extension)->assertOk();
        $this->get($host.'/.well-known/dasti-verificacion/'.$dominio->token)->assertOk()->assertSee($dominio->token);
        $this->get($host.'/.well-known/dasti-verificacion/dasti-otro-token-invalido')->assertNotFound();
        $this->get($host.'/ir/'.$pagina->publicado['paginas'][0]['bloques'][0]['id'])->assertRedirect('https://cafe-aurora.mx/menu');
        $this->get($host.'/qr.svg')->assertOk();

        // Host desconocido o no verificado.
        $this->get('https://desconocido.mx/')->assertNotFound();
        $this->get('https://desconocido.mx/login')->assertNotFound();
    }

    public function test_autorizacion_de_certificados_para_caddy(): void
    {
        [, $dominio] = $this->dominioActivo();
        $pendiente = $this->gestor()->registrar($dominio->pagina, 'pendiente.cafe-aurora.mx', $this->propietario($dominio->pagina->negocio));

        $this->get('http://localhost/interno/dominios/autorizar?domain=enlaces.cafe-aurora.mx')->assertOk();
        $this->get('http://localhost/interno/dominios/autorizar?domain='.$pendiente->host)->assertNotFound();
        $this->get('http://localhost/interno/dominios/autorizar?domain=desconocido.mx')->assertNotFound();
        $this->get('http://localhost/interno/dominios/autorizar?domain=redireccion.test')->assertOk();

        // Desde fuera (a través del proxy): prohibido salvo con el secreto.
        $this->withHeader('X-Forwarded-For', '198.51.100.20')
            ->get('http://localhost/interno/dominios/autorizar?domain=enlaces.cafe-aurora.mx')->assertForbidden();
        config(['dasti.paginas.dominios.secreto_autorizacion' => 'secreto-largo']);
        $this->withHeader('X-Forwarded-For', '198.51.100.20')
            ->get('http://localhost/interno/dominios/autorizar?domain=enlaces.cafe-aurora.mx&secreto=secreto-largo')->assertOk();
    }

    public function test_revision_programada_activa_los_dominios_configurados(): void
    {
        $negocio = $this->negocioConPaginas();
        $pagina = $this->publicar($negocio, $this->documento([$this->enlace()]));
        $dominio = $this->gestor()->registrar($pagina, 'links.cafe.mx', $this->propietario($negocio));
        $this->apuntar($dominio);
        $dominio->forceFill(['proxima_revision_en' => now()->subMinute()])->save();

        $this->artisan('dasti:revisar-dominios')->assertSuccessful();

        $this->assertSame(EstadoDominio::Activo, $dominio->fresh()?->estado);
    }

    public function test_panel_de_dominios_aislado_por_negocio(): void
    {
        [$pagina, $dominio] = $this->dominioActivo();
        $ajeno = $this->negocioConPaginas();
        $usuarioAjeno = $this->propietario($ajeno);

        $this->actingAs($usuarioAjeno)->post(route('cliente.paginas.dominios.verificar', $dominio))->assertNotFound();
        $this->actingAs($usuarioAjeno)->delete(route('cliente.paginas.dominios.destroy', $dominio))->assertNotFound();
        $this->assertNotNull(DominioPagina::query()->find($dominio->id));

        $propietario = $this->propietario($pagina->negocio);
        $this->actingAs($propietario)->get(route('cliente.paginas.dominios.index'))->assertOk()
            ->assertInertia(fn ($p) => $p->component('cliente/paginas/Dominios')
                ->where('dominios.0.host', 'enlaces.cafe-aurora.mx')
                ->where('dominios.0.estado', 'activo')
                ->where('dominios.0.instrucciones.registros.0.tipo', 'TXT')
                ->where('dominios.0.instrucciones.registros.1.tipo', 'CNAME')
                ->missing('dominios.0.token'));

        $this->actingAs($propietario)->post(route('cliente.paginas.dominios.store'), ['host' => 'nuevo.cafe-aurora.mx'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('paginas_enlaces_dominios', ['host' => 'nuevo.cafe-aurora.mx', 'negocio_id' => $pagina->negocio_id]);
        $this->actingAs($propietario)->post(route('cliente.paginas.dominios.store'), ['host' => 'dasti.cloud'])->assertSessionHasErrors('host');
    }
}
