<?php

namespace Tests\Feature\Fidelidad;

use App\Models\ArchivoPublico;
use App\Models\Contratacion;
use App\Models\Fidelidad\Campana;
use App\Models\Fidelidad\Consumidor;
use App\Models\Fidelidad\Diseno;
use App\Models\Fidelidad\DisenoVersion;
use App\Models\Fidelidad\Movimiento;
use App\Models\Fidelidad\Participacion;
use App\Models\Fidelidad\Recompensa;
use App\Models\Negocio;
use App\Models\User;
use App\Services\Fidelidad\Diseno\DocumentoDiseno;
use App\Services\Fidelidad\GestorConsumidores;
use App\Services\Fidelidad\RegistroVisitas;
use App\Services\Servicios\AccesoServicios;
use App\Services\Servicios\GestorContrataciones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\PreparaFidelidad;
use Tests\TestCase;

/**
 * Diseño de la tarjeta digital (Tarea A): permisos + contratación,
 * saneamiento, publicación, historial, variantes y página pública. El
 * diseño nunca altera visitas, metas ni recompensas.
 */
class DisenoTarjetaTest extends TestCase
{
    use PreparaFidelidad, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function documento(array $cambios = []): array
    {
        return array_replace_recursive(DocumentoDiseno::predeterminado(), $cambios);
    }

    private function guardar(User $usuario, array $documento, int $version = 0, ?int $campana = null): TestResponse
    {
        return $this->actingAs($usuario)->putJson(route('cliente.fidelidad.diseno.update'), [
            'campana_id' => $campana, 'version' => $version, 'documento' => $documento,
        ]);
    }

    private function publicar(User $usuario, array $documento, int $version = 0, ?int $campana = null): TestResponse
    {
        return $this->actingAs($usuario)->postJson(route('cliente.fidelidad.diseno.publicar'), [
            'campana_id' => $campana, 'version' => $version, 'documento' => $documento,
        ]);
    }

    /**
     * Datos JSON incrustados en la página pública.
     *
     * @return array<string, mixed>
     */
    private function datosPublicos(TestResponse $respuesta): array
    {
        preg_match('#<script type="application/json" id="datos-fidelidad">(.*?)</script>#s', (string) $respuesta->getContent(), $m);
        $this->assertNotEmpty($m, 'La página incrusta los datos de la tarjeta');

        return json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
    }

    private function imagen(Negocio $negocio): ArchivoPublico
    {
        $gd = imagecreatetruecolor(20, 20);
        ob_start();
        imagepng($gd);
        $png = (string) ob_get_clean();

        $this->actingAs($this->propietario($negocio))->post(route('cliente.fidelidad.diseno.imagenes.store'), [
            'imagen' => UploadedFile::fake()->createWithContent('logo.png', $png),
            'uso' => 'logo',
        ], ['Accept' => 'application/json'])->assertOk();

        return ArchivoPublico::query()->where('negocio_id', $negocio->id)->latest('id')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function instantaneaOperacion(): array
    {
        return [
            'consumidores' => Consumidor::query()->orderBy('id')->get()->toArray(),
            'participaciones' => Participacion::query()->orderBy('id')->get()->toArray(),
            'movimientos' => Movimiento::query()->orderBy('id')->get()->toArray(),
            'recompensas' => Recompensa::query()->orderBy('id')->get()->toArray(),
        ];
    }

    // ---------------------------------------------------------------

    public function test_sin_permiso_no_se_ve_ni_se_edita(): void
    {
        $negocio = $this->negocioConFidelidad();
        $empleado = $this->empleado($negocio); // el rol empleado no tiene fidelidad.diseno

        $this->actingAs($empleado)->get(route('cliente.fidelidad.diseno.edit'))->assertForbidden();
        $this->guardar($empleado, $this->documento())->assertForbidden();
        $this->publicar($empleado, $this->documento())->assertForbidden();
        $this->assertSame(0, Diseno::query()->count());
    }

    public function test_sin_contratacion_no_se_edita_y_en_solo_lectura_se_explica(): void
    {
        $sinServicio = Negocio::factory()->create();
        $propietario = $this->propietario($sinServicio);

        $this->actingAs($propietario)->get(route('cliente.fidelidad.diseno.edit'))
            ->assertRedirect(route('cliente.servicios.show', 'fidelidad'));
        $this->guardar($propietario, $this->documento())->assertForbidden();

        $negocio = $this->negocioConFidelidad();
        app(GestorContrataciones::class)->restringir(Contratacion::query()->where('negocio_id', $negocio->id)->firstOrFail(), 'Disputa', manual: true);
        app(AccesoServicios::class)->olvidar();
        $duenio = $this->propietario($negocio);

        $this->actingAs($duenio)->get(route('cliente.fidelidad.diseno.edit'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('cliente/fidelidad/Diseno')
                ->where('edicion.puede', false)
                ->where('edicion.motivo', fn ($m) => str_contains((string) $m, 'solo lectura')));
        $this->guardar($duenio, $this->documento())->assertForbidden();
        $this->assertSame(0, Diseno::query()->count());
    }

    public function test_el_editor_recibe_plantillas_campanas_reales_y_ningun_dato_personal(): void
    {
        $negocio = $this->negocioConFidelidad();
        $campana = $this->campana($negocio, ['nombre' => 'Diez cafés', 'meta_visitas' => 10]);
        $real = $this->consumidor($negocio, ['nombre' => 'Consumidora Real', 'telefono' => '5544332211']);
        app(RegistroVisitas::class)->registrarVisita($this->propietario($negocio), $real, $campana, $this->uuid());

        $respuesta = $this->actingAs($this->propietario($negocio))->get(route('cliente.fidelidad.diseno.edit'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('cliente/fidelidad/Diseno')
                ->where('edicion.puede', true)
                ->where('diseno.existe', false)
                ->where('campanas.0.id', $campana->id)
                ->where('campanas.0.meta', 10)
                ->where('campanas.0.participantes', 1)
                ->has('plantillas', 8));

        $this->assertStringNotContainsString('Consumidora Real', (string) $respuesta->getContent());
        $this->assertStringNotContainsString('5544332211', (string) $respuesta->getContent());
    }

    public function test_guardar_borrador_no_publica(): void
    {
        $negocio = $this->negocioConFidelidad();
        $this->campana($negocio);
        $tarjeta = $this->tarjeta($this->consumidor($negocio));
        $propietario = $this->propietario($negocio);

        $this->guardar($propietario, $this->documento(['programa' => ['nombre' => 'Club Borrador']]))->assertOk()
            ->assertJsonPath('diseno.version', 1)
            ->assertJsonPath('diseno.borrador.programa.nombre', 'Club Borrador')
            ->assertJsonPath('diseno.publicado', null)
            ->assertJsonPath('diseno.cambios_sin_publicar', true);

        $datos = $this->datosPublicos($this->get(route('fidelidad.publico.credencial', $tarjeta->codigo))->assertOk());
        $this->assertSame(DocumentoDiseno::predeterminado()['programa']['nombre'], $datos['disenos']['general']['programa']['nombre']);
    }

    public function test_saneamiento_de_entradas_maliciosas(): void
    {
        $negocio = $this->negocioConFidelidad();
        $otro = $this->negocioConFidelidad();
        $ajena = $this->imagen($otro);
        $propia = $this->imagen($negocio);

        $respuesta = $this->guardar($this->propietario($negocio), $this->documento([
            'programa' => ['nombre' => '<script>alert(1)</script>Club <b>VIP</b>', 'lema' => "Hola\u{202E}\u{0007} mundo"],
            'identidad' => ['logo' => $ajena->uuid, 'portada' => 'javascript:alert(1)'],
            'pagina' => ['fondo_tipo' => 'imagen', 'imagen' => 'data:image/svg+xml;base64,PHN2Zz4=', 'decoracion' => 'url(evil)'],
            'tarjeta' => [
                'color1' => 'red;background:url(javascript:alert(1))',
                'color2' => '#ABC',
                'texto' => 'expression(alert(1))',
                'radio' => 9999,
                'borde_ancho' => -5,
                'oscurecer' => '50',
                'imagen' => $propia->uuid,
                'sombra' => '0 0 0 red; }</style><script>',
            ],
            'tipografia' => ['titulos' => "Comic Sans'; @import url(//evil)"],
            'progreso' => ['presentacion' => 'cohete', 'icono' => '<svg onload=alert(1)>', 'mostrar_barra' => 'si'],
            'contenido' => [
                'secciones' => array_fill(0, 9, ['titulo' => '<img src=x onerror=alert(1)>Horario', 'elementos' => array_fill(0, 20, 'Lunes a viernes')]),
                'terminos' => str_repeat('T', 5000),
            ],
            'meta_visitas' => 1,
            'extra' => ['html' => '<iframe>'],
        ]))->assertOk();

        $doc = Diseno::query()->firstOrFail()->borrador;
        $json = (string) json_encode($doc);
        $base = DocumentoDiseno::predeterminado();

        $this->assertStringNotContainsStringIgnoringCase('<script', $json);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $json);
        $this->assertStringNotContainsStringIgnoringCase('data:image', $json);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $json);
        $this->assertStringNotContainsString('<', $json);
        $this->assertSame('alert(1)Club VIP', $doc['programa']['nombre']);
        $this->assertSame('Hola mundo', $doc['programa']['lema']);
        $this->assertNull($doc['identidad']['logo'], 'Archivo de otro negocio rechazado');
        $this->assertNull($doc['identidad']['portada']);
        $this->assertNull($doc['pagina']['imagen']);
        $this->assertSame($base['pagina']['decoracion'], $doc['pagina']['decoracion']);
        $this->assertSame($base['tarjeta']['color1'], $doc['tarjeta']['color1']);
        $this->assertSame('#aabbcc', $doc['tarjeta']['color2']);
        $this->assertSame($base['tarjeta']['texto'], $doc['tarjeta']['texto']);
        $this->assertSame(40, $doc['tarjeta']['radio']);
        $this->assertSame(0, $doc['tarjeta']['borde_ancho']);
        $this->assertSame(50, $doc['tarjeta']['oscurecer']);
        $this->assertSame($propia->uuid, $doc['tarjeta']['imagen'], 'Imagen propia aceptada');
        $this->assertSame($base['tarjeta']['sombra'], $doc['tarjeta']['sombra']);
        $this->assertSame($base['tipografia']['titulos'], $doc['tipografia']['titulos']);
        $this->assertSame($base['progreso']['presentacion'], $doc['progreso']['presentacion']);
        $this->assertSame($base['progreso']['icono'], $doc['progreso']['icono']);
        $this->assertFalse($doc['progreso']['mostrar_barra']);
        $this->assertCount(DocumentoDiseno::MAX_SECCIONES, $doc['contenido']['secciones']);
        $this->assertCount(DocumentoDiseno::MAX_ELEMENTOS, $doc['contenido']['secciones'][0]['elementos']);
        $this->assertSame('Horario', $doc['contenido']['secciones'][0]['titulo']);
        $this->assertSame(2000, mb_strlen($doc['contenido']['terminos']));
        $this->assertArrayNotHasKey('meta_visitas', $doc);
        $this->assertArrayNotHasKey('extra', $doc);
        $this->assertNotEmpty($respuesta->json('avisos'));

        // El mismo saneamiento aplica al publicar.
        $this->publicar($this->propietario($negocio), $this->documento(['tarjeta' => ['color1' => 'url(x)'], 'identidad' => ['logo' => $ajena->uuid]]), 1)->assertOk();
        $publicado = Diseno::query()->firstOrFail()->publicado;
        $this->assertSame($base['tarjeta']['color1'], $publicado['tarjeta']['color1'] ?? null);
        $this->assertArrayHasKey('logo', $publicado['identidad']);
        $this->assertNull($publicado['identidad']['logo']);

        // Documento ausente o demasiado grande.
        $this->actingAs($this->propietario($negocio))->putJson(route('cliente.fidelidad.diseno.update'), ['version' => 2])->assertUnprocessable();
        $this->guardar($this->propietario($negocio), ['relleno' => str_repeat('x', DocumentoDiseno::MAX_BYTES + 10)], 2)
            ->assertUnprocessable()->assertJsonValidationErrors('documento');
    }

    public function test_publicar_muestra_el_diseno_con_sellos_reales_y_sin_datos_de_contacto(): void
    {
        $negocio = $this->negocioConFidelidad();
        $campana = $this->campana($negocio, ['nombre' => 'Diez visitas', 'meta_visitas' => 10, 'max_visitas_por_dia' => 10]);
        $consumidor = $this->consumidor($negocio, ['nombre' => 'Ana López', 'alias' => 'Anita', 'telefono' => '5512349876', 'email' => 'ana@correo.test']);
        $tarjeta = $this->tarjeta($consumidor);
        $propietario = $this->propietario($negocio);

        for ($i = 0; $i < 6; $i++) {
            app(RegistroVisitas::class)->registrarVisita($propietario, $consumidor->fresh() ?? $consumidor, $campana, $this->uuid());
        }

        $logo = $this->imagen($negocio);
        $this->publicar($propietario, $this->documento([
            'programa' => ['nombre' => 'Club Café Aroma'],
            'identidad' => ['logo' => $logo->uuid],
            'tarjeta' => ['color1' => '#4a2a1a'],
            'progreso' => ['presentacion' => 'ruta', 'icono' => 'cafe'],
        ]))->assertOk()->assertJsonPath('diseno.publicado.programa.nombre', 'Club Café Aroma')
            ->assertJsonPath('versiones.0.motivo', 'Publicación');

        $respuesta = $this->get(route('fidelidad.publico.credencial', $tarjeta->codigo))->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('Llevas 6 de 10 visitas.', false)
            ->assertSee('https://fonts.bunny.net/css?family=', false)
            ->assertDontSee('5512349876')->assertDontSee('ana@correo.test')->assertDontSee('López');
        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));

        $datos = $this->datosPublicos($respuesta);
        $this->assertSame('Club Café Aroma', $datos['disenos']['general']['programa']['nombre']);
        $this->assertSame('ruta', $datos['disenos']['general']['progreso']['presentacion']);
        $this->assertSame('Anita', $datos['consumidor']);
        $this->assertSame(6, $datos['programas'][0]['visitas']);
        $this->assertSame(10, $datos['programas'][0]['meta']);
        $this->assertSame(4, $datos['programas'][0]['faltan']);
        $this->assertSame($logo->url(), $datos['medios'][$logo->uuid]);
        $this->assertArrayNotHasKey('id', $datos['programas'][0]);
        $this->assertSame(0, Movimiento::query()->where('tipo', 'visita')->count() - 6, 'Abrir la página no suma visitas');

        // El enlace digital usa el mismo diseño publicado.
        $token = app(GestorConsumidores::class)->emitirEnlaceDigital($consumidor, $propietario, 'Prueba');
        $this->assertSame('Club Café Aroma', $this->datosPublicos($this->get(route('fidelidad.publico.enlace', $token))->assertOk())['disenos']['general']['programa']['nombre']);
    }

    public function test_cambiar_y_publicar_un_diseno_no_altera_el_progreso(): void
    {
        $negocio = $this->negocioConFidelidad();
        $campana = $this->campana($negocio, ['meta_visitas' => 2]);
        $consumidor = $this->consumidor($negocio);
        $tarjeta = $this->tarjeta($consumidor);
        $propietario = $this->propietario($negocio);
        $registro = app(RegistroVisitas::class);
        $registro->registrarVisita($propietario, $consumidor, $campana, $this->uuid());
        $this->travel(1)->days();
        $registro->registrarVisita($propietario, $consumidor->fresh() ?? $consumidor, $campana, $this->uuid());
        $this->travel(1)->days();
        $registro->registrarVisita($propietario, $consumidor->fresh() ?? $consumidor, $campana, $this->uuid());

        $antes = $this->instantaneaOperacion();
        $paginaAntes = $this->datosPublicos($this->get(route('fidelidad.publico.credencial', $tarjeta->codigo)))['programas'];

        $this->publicar($propietario, $this->documento(['progreso' => ['presentacion' => 'cuadricula']]))->assertOk();
        $this->guardar($propietario, $this->documento(['progreso' => ['presentacion' => 'premium']]), 1)->assertOk();
        $this->publicar($propietario, $this->documento(['progreso' => ['presentacion' => 'boleto']]), 2)->assertOk();

        $this->assertEquals($antes, $this->instantaneaOperacion());
        $paginaDespues = $this->datosPublicos($this->get(route('fidelidad.publico.credencial', $tarjeta->codigo)))['programas'];
        $this->assertSame($paginaAntes, $paginaDespues);
        $this->assertSame('disponible', $paginaDespues[0]['estado']);
        $this->assertSame(1, $paginaDespues[0]['visitas']);
        $this->assertSame('Un café americano', $paginaDespues[0]['recompensas'][0]['descripcion']);
    }

    public function test_historial_y_restauracion_de_version(): void
    {
        $negocio = $this->negocioConFidelidad();
        $propietario = $this->propietario($negocio);

        $this->publicar($propietario, $this->documento(['programa' => ['nombre' => 'Primera']]))->assertOk();
        $this->publicar($propietario, $this->documento(['programa' => ['nombre' => 'Segunda']]), 1)->assertOk();
        $this->guardar($propietario, $this->documento(['programa' => ['nombre' => 'Borrador sin publicar']]), 2)->assertOk();

        $primera = DisenoVersion::query()->orderBy('id')->firstOrFail();
        $this->actingAs($propietario)->postJson(route('cliente.fidelidad.diseno.restaurar', $primera), ['version' => 3])->assertOk()
            ->assertJsonPath('diseno.borrador.programa.nombre', 'Primera')
            ->assertJsonPath('diseno.publicado.programa.nombre', 'Segunda')
            ->assertJsonPath('diseno.version', 4);

        // El borrador previo quedó respaldado.
        $this->assertTrue(DisenoVersion::query()->where('motivo', DisenoVersion::RESPALDO)->get()
            ->contains(fn (DisenoVersion $v) => $v->documento['programa']['nombre'] === 'Borrador sin publicar'));
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'fidelidad.diseno_restaurado']);

        // Versión obsoleta: conflicto, nada se sobrescribe.
        $this->guardar($propietario, $this->documento(['programa' => ['nombre' => 'Pestaña vieja']]), 2)->assertStatus(409);
        $this->assertSame('Primera', Diseno::query()->firstOrFail()->borrador['programa']['nombre']);
    }

    public function test_variante_por_campana(): void
    {
        $negocio = $this->negocioConFidelidad();
        $cafe = $this->campana($negocio, ['nombre' => 'Café', 'meta_visitas' => 8]);
        $corte = $this->campana($negocio, ['nombre' => 'Cortes', 'meta_visitas' => 5]);
        $consumidor = $this->consumidor($negocio);
        $tarjeta = $this->tarjeta($consumidor);
        $propietario = $this->propietario($negocio);
        app(RegistroVisitas::class)->registrarVisita($propietario, $consumidor, $cafe, $this->uuid());
        app(RegistroVisitas::class)->registrarVisita($propietario, $consumidor->fresh() ?? $consumidor, $corte, $this->uuid());

        $this->publicar($propietario, $this->documento(['programa' => ['nombre' => 'General']]))->assertOk();
        $this->publicar($propietario, $this->documento(['programa' => ['nombre' => 'Barbería'], 'progreso' => ['presentacion' => 'boleto']]), 0, $corte->id)->assertOk();

        $this->actingAs($propietario)->get(route('cliente.fidelidad.diseno.edit', ['campana' => $corte->id]))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('variante.campana_id', $corte->id)
                ->where('diseno.publicado.programa.nombre', 'Barbería')
                ->where('general.programa.nombre', 'General'));

        $datos = $this->datosPublicos($this->get(route('fidelidad.publico.credencial', $tarjeta->codigo)));
        $porCampana = collect($datos['programas'])->keyBy('campana');
        $this->assertNull($porCampana['Café']['variante']);
        $this->assertSame(8, $porCampana['Café']['meta']);
        $this->assertSame(5, $porCampana['Cortes']['meta']);
        $this->assertSame('Barbería', $datos['disenos']['variantes'][$porCampana['Cortes']['variante']]['programa']['nombre']);
        $this->assertSame('General', $datos['disenos']['general']['programa']['nombre']);

        // Quitar la variante: vuelve al diseño general.
        $this->actingAs($propietario)->deleteJson(route('cliente.fidelidad.diseno.variantes.destroy', $corte))->assertOk();
        $datos = $this->datosPublicos($this->get(route('fidelidad.publico.credencial', $tarjeta->codigo)));
        $this->assertSame([], $datos['disenos']['variantes']);
        $this->assertSame(2, Participacion::query()->count());
    }

    public function test_aislamiento_entre_negocios(): void
    {
        $negocio = $this->negocioConFidelidad();
        $otro = $this->negocioConFidelidad();
        $campanaAjena = $this->campana($otro);
        $ajeno = $this->propietario($otro);
        $propietario = $this->propietario($negocio);
        $archivo = $this->imagen($negocio);

        $this->publicar($propietario, $this->documento(['programa' => ['nombre' => 'Secreto'], 'identidad' => ['logo' => $archivo->uuid]]))->assertOk();
        $version = DisenoVersion::query()->firstOrFail();

        // El otro negocio no ve, no restaura, no usa campañas ni archivos ajenos.
        $this->actingAs($ajeno)->get(route('cliente.fidelidad.diseno.edit'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('diseno.existe', false)->where('versiones', [])->where('archivos', []));
        $this->actingAs($ajeno)->postJson(route('cliente.fidelidad.diseno.restaurar', $version), ['version' => 0])->assertNotFound();
        $this->guardar($ajeno, $this->documento(), 0, $this->campana($negocio)->id)->assertNotFound();
        $this->actingAs($propietario)->get(route('cliente.fidelidad.diseno.edit', ['campana' => $campanaAjena->id]))->assertNotFound();
        $this->guardar($ajeno, $this->documento(['identidad' => ['logo' => $archivo->uuid]]))->assertOk();
        $this->assertNull(Diseno::query()->where('negocio_id', $otro->id)->firstOrFail()->borrador['identidad']['logo']);
        $this->assertSame('Secreto', Diseno::query()->where('negocio_id', $negocio->id)->firstOrFail()->publicado['programa']['nombre'] ?? null);
    }

    public function test_suspension_y_reactivacion(): void
    {
        $admin = User::factory()->create();
        $negocio = $this->negocioConFidelidad();
        $this->campana($negocio);
        $tarjeta = $this->tarjeta($this->consumidor($negocio));
        $propietario = $this->propietario($negocio);
        $this->publicar($propietario, $this->documento(['programa' => ['nombre' => 'Club Activo']]))->assertOk();
        $contratacion = Contratacion::query()->where('negocio_id', $negocio->id)->firstOrFail();

        app(GestorContrataciones::class)->suspender($contratacion, 'Falta de pago', $admin);
        app(AccesoServicios::class)->olvidar();

        $this->actingAs($propietario)->get(route('cliente.fidelidad.diseno.edit'))->assertRedirect(route('cliente.servicios.show', 'fidelidad'));
        $this->publicar($propietario, $this->documento(), 1)->assertForbidden();

        // La página pública sigue mostrando un estado coherente y de solo lectura.
        auth()->logout();
        $datos = $this->datosPublicos($this->get(route('fidelidad.publico.credencial', $tarjeta->codigo))->assertOk());
        $this->assertSame('Club Activo', $datos['disenos']['general']['programa']['nombre']);
        $this->assertNotNull($datos['aviso']);
        $this->assertSame(1, Diseno::query()->value('version'));

        app(GestorContrataciones::class)->reactivar($contratacion->fresh() ?? $contratacion, 'Pago aclarado', $admin);
        app(AccesoServicios::class)->olvidar();
        $this->actingAs($propietario)->get(route('cliente.fidelidad.diseno.edit'))->assertOk();
        $this->publicar($propietario, $this->documento(['programa' => ['nombre' => 'De vuelta']]), 1)->assertOk();
        auth()->logout();
        $this->assertNull($this->datosPublicos($this->get(route('fidelidad.publico.credencial', $tarjeta->codigo)))['aviso']);
    }

    public function test_subida_de_imagenes_valida_el_contenido(): void
    {
        $negocio = $this->negocioConFidelidad();
        $propietario = $this->propietario($negocio);

        $this->actingAs($propietario)->post(route('cliente.fidelidad.diseno.imagenes.store'), [
            'imagen' => UploadedFile::fake()->createWithContent('falsa.png', '<svg onload="alert(1)"></svg>'),
            'uso' => 'logo',
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('imagen');

        $archivo = $this->imagen($negocio);
        $this->assertSame(ArchivoPublico::MODULO_FIDELIDAD, $archivo->modulo);
        $this->assertContains($archivo->extension, ['webp', 'png']);
        Storage::disk('local')->assertExists($archivo->ruta);

        $this->actingAs($this->empleado($negocio))->post(route('cliente.fidelidad.diseno.imagenes.store'), [
            'imagen' => UploadedFile::fake()->image('x.png'), 'uso' => 'logo',
        ], ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_la_pagina_publica_escapa_textos_y_tiene_alternativa_sin_javascript(): void
    {
        $negocio = $this->negocioConFidelidad();
        $campana = Campana::query()->findOrFail($this->campana($negocio)->id);
        $campana->forceFill(['nombre' => '</script><script>alert(1)</script>'])->save();
        $consumidor = $this->consumidor($negocio, ['alias' => '<img src=x onerror=alert(1)>']);
        $tarjeta = $this->tarjeta($consumidor);
        app(RegistroVisitas::class)->registrarVisita($this->propietario($negocio), $consumidor, $campana, $this->uuid());

        $html = (string) $this->get(route('fidelidad.publico.credencial', $tarjeta->codigo))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('<noscript>', $html);
        $this->assertStringContainsString('Llevas 1 de 3 visitas.', $html);
        $this->assertStringContainsString('Te faltan 2 para: Un café americano', $html);
    }

    public function test_tarjeta_inactiva_y_recuperacion_siguen_funcionando(): void
    {
        $this->get(route('fidelidad.publico.credencial', Str::lower(str_repeat('a', 16))))->assertNotFound()->assertSee('Tarjeta no disponible');
        $this->get(route('fidelidad.publico.recuperar'))->assertOk()->assertSee('Recuperar mi enlace')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->post(route('fidelidad.publico.recuperar.enviar'), ['email' => 'nadie@correo.test'])
            ->assertRedirect(route('fidelidad.publico.recuperar'));
    }
}
