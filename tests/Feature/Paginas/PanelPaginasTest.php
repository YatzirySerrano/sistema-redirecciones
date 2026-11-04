<?php

namespace Tests\Feature\Paginas;

use App\Enums\EstadoContratacion;
use App\Enums\OrigenContratacion;
use App\Models\ArchivoPublico;
use App\Models\Contratacion;
use App\Models\Negocio;
use App\Models\Paginas\MensajePagina;
use App\Models\Paginas\PaginaEnlaces;
use App\Models\Paginas\VersionPagina;
use App\Models\Rol;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Paginas\GestorPaginas;
use App\Services\Paginas\PlantillasPagina;
use App\Services\Servicios\AccesoServicios;
use App\Services\Servicios\GestorContrataciones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\PreparaPaginas;
use Tests\TestCase;

class PanelPaginasTest extends TestCase
{
    use PreparaPaginas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    private function editor(User $usuario): PaginaEnlaces
    {
        $this->actingAs($usuario)->get(route('cliente.paginas.editor'))->assertOk();

        return PaginaEnlaces::query()->where('negocio_id', $usuario->negocio_id)->firstOrFail();
    }

    public function test_sin_contratacion_el_permiso_no_basta(): void
    {
        $negocio = Negocio::factory()->create();
        $usuario = $this->propietario($negocio);
        $this->assertTrue($usuario->tienePermiso('paginas.editar'));

        $this->actingAs($usuario)->get(route('cliente.paginas.inicio'))->assertRedirect(route('cliente.servicios.show', 'pagina_enlaces'));
        $this->actingAs($usuario)->get(route('cliente.paginas.editor'))->assertRedirect(route('cliente.servicios.show', 'pagina_enlaces'));
        $this->actingAs($usuario)->putJson(route('cliente.paginas.borrador'), ['documento' => ['perfil' => []], 'version' => 1])->assertForbidden();
        $this->actingAs($usuario)->postJson(route('cliente.paginas.publicar'), ['version' => 1])->assertForbidden();
        $this->assertSame(0, PaginaEnlaces::query()->count());

        // Contratar Fidelidad no habilita la Página de enlaces.
        app(GestorContrataciones::class)->activar($negocio, Servicio::fidelidad(), OrigenContratacion::Manual, motivo: 'Prueba');
        $this->actingAs($usuario)->get(route('cliente.paginas.inicio'))->assertRedirect(route('cliente.servicios.show', 'pagina_enlaces'));
    }

    public function test_funciona_tras_activacion_manual_y_tras_pago_en_linea(): void
    {
        foreach ([OrigenContratacion::Manual, OrigenContratacion::Openpay] as $origen) {
            $negocio = $this->negocioConPaginas(origen: $origen);
            $usuario = $this->propietario($negocio);

            $this->actingAs($usuario)->get(route('cliente.paginas.inicio'))->assertOk()
                ->assertInertia(fn (Assert $p) => $p->component('cliente/paginas/Inicio')
                    ->where('servicio.activo', true)
                    ->where('puede.editar', true)
                    ->where('pagina.estado', 'borrador'));
        }

        $this->assertSame(1, Contratacion::query()->where('origen', OrigenContratacion::Openpay)->whereNotNull('pago_id')->count());
    }

    public function test_permiso_sin_edicion_ve_en_solo_lectura(): void
    {
        $negocio = $this->negocioConPaginas();
        $rol = new Rol;
        $rol->forceFill(['name' => 'Solo ver', 'guard_name' => 'web', 'ambito' => 'negocio'])->save();
        $rol->syncPermissions(['panel.ver', 'paginas.ver']);
        $lector = User::factory()->cliente($negocio)->conRol($rol)->create();

        $this->actingAs($lector)->get(route('cliente.paginas.editor'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('puede.editar', false)->where('puede.publicar', false));
        $this->actingAs($lector)->putJson(route('cliente.paginas.borrador'), ['documento' => [], 'version' => 1])->assertForbidden();
        $this->actingAs($lector)->get(route('cliente.paginas.mensajes.index'))->assertForbidden();
    }

    public function test_guardar_borrador_con_control_de_concurrencia_y_errores_por_elemento(): void
    {
        $negocio = $this->negocioConPaginas();
        $usuario = $this->propietario($negocio);
        $pagina = $this->editor($usuario);
        $enlace = $this->enlace('Menú', 'https://cafe-aurora.mx/menu');

        $respuesta = $this->actingAs($usuario)->putJson(route('cliente.paginas.borrador'), [
            'documento' => $this->documento([$enlace]),
            'version' => $pagina->version,
        ])->assertOk()->assertJsonPath('documento.paginas.0.bloques.0.id', $enlace['id']);
        $nueva = (int) $respuesta->json('pagina.version');
        $this->assertSame($pagina->version + 1, $nueva);
        $this->assertNull($pagina->fresh()?->publicado, 'Guardar el borrador no publica.');

        // Otra pestaña con la versión anterior: 409 sin sobrescribir.
        $this->actingAs($usuario)->putJson(route('cliente.paginas.borrador'), [
            'documento' => $this->documento([$this->enlace('Otro')]),
            'version' => $pagina->version,
        ])->assertStatus(409)->assertJsonPath('conflicto.version', $nueva);
        $this->assertSame('Menú', $pagina->fresh()?->borrador['paginas'][0]['bloques'][0]['titulo']);

        // Errores asociados al elemento que los causa.
        $malo = $this->enlace('Malo', 'javascript:alert(1)');
        $this->actingAs($usuario)->putJson(route('cliente.paginas.borrador'), ['documento' => $this->documento([$malo]), 'version' => $nueva])
            ->assertStatus(422)->assertJsonValidationErrors("bloques.{$malo['id']}.url");
    }

    public function test_publicar_despublicar_reordenar_historial_y_restaurar(): void
    {
        $negocio = $this->negocioConPaginas();
        $usuario = $this->propietario($negocio);
        $pagina = $this->editor($usuario);
        [$a, $b, $c] = [$this->enlace('A'), $this->enlace('B'), $this->enlace('C')];

        $version = (int) $this->actingAs($usuario)->putJson(route('cliente.paginas.borrador'), ['documento' => $this->documento([$a, $b, $c]), 'version' => $pagina->version])->json('pagina.version');

        // Publicar con elementos incompletos: errores claros y registro de incidencia.
        $incompleto = $this->enlace('', '');
        $version = (int) $this->actingAs($usuario)->putJson(route('cliente.paginas.borrador'), ['documento' => $this->documento([$a, $b, $c, $incompleto]), 'version' => $version])->json('pagina.version');
        $this->actingAs($usuario)->postJson(route('cliente.paginas.publicar'), ['version' => $version])
            ->assertStatus(422)->assertJsonValidationErrors(["bloques.{$incompleto['id']}.titulo", "bloques.{$incompleto['id']}.url"]);
        $this->assertNotNull($pagina->fresh()?->incidencia);

        // Reordenar (C, A, B) y publicar.
        $version = (int) $this->actingAs($usuario)->putJson(route('cliente.paginas.borrador'), ['documento' => $this->documento([$c, $a, $b]), 'version' => $version])->json('pagina.version');
        $this->actingAs($usuario)->postJson(route('cliente.paginas.publicar'), ['version' => $version])->assertOk()->assertJsonPath('pagina.estado', 'publicado');
        $pagina = $pagina->fresh();
        $this->assertNotNull($pagina);
        $this->assertSame([$c['id'], $a['id'], $b['id']], array_column($pagina->publicado['paginas'][0]['bloques'], 'id'));
        $this->assertNull($pagina->incidencia);
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'pagina.publicada', 'negocio_id' => $negocio->id]);
        $this->get('/l/'.$pagina->slug)->assertOk();

        // Restaurar una versión anterior (al borrador, no publica).
        $copia = VersionPagina::query()->where('pagina_id', $pagina->id)->where('motivo', 'guardado')->oldest('id')->firstOrFail();
        $this->actingAs($usuario)->postJson(route('cliente.paginas.versiones.restaurar', $copia), ['version' => $pagina->version])->assertOk();
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'pagina.version_restaurada']);
        $this->assertSame([$c['id'], $a['id'], $b['id']], array_column($pagina->fresh()?->publicado['paginas'][0]['bloques'] ?? [], 'id'));
        $this->assertTrue(VersionPagina::query()->where('pagina_id', $pagina->id)->where('motivo', 'antes_de_restaurar')->exists());
        $this->actingAs($usuario)->getJson(route('cliente.paginas.versiones'))->assertOk()->assertJsonStructure(['versiones' => [['id', 'motivo', 'etiqueta', 'en']]]);

        // Una versión de otro negocio no se puede consultar ni restaurar.
        $ajeno = $this->propietario($this->negocioConPaginas());
        $this->editor($ajeno);
        $this->actingAs($ajeno)->getJson(route('cliente.paginas.versiones.show', $copia))->assertNotFound();
        $this->actingAs($ajeno)->postJson(route('cliente.paginas.versiones.restaurar', $copia), ['version' => 1])->assertNotFound();

        $this->actingAs($usuario)->postJson(route('cliente.paginas.despublicar'))->assertOk()->assertJsonPath('pagina.estado', 'despublicado');
        $this->get('/l/'.$pagina->slug)->assertNotFound();
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'pagina.despublicada']);
    }

    public function test_servicio_vencido_queda_en_solo_lectura(): void
    {
        $negocio = $this->negocioConPaginas();
        $usuario = $this->propietario($negocio);
        $pagina = $this->editor($usuario);
        Contratacion::query()->where('negocio_id', $negocio->id)->update(['estado' => EstadoContratacion::Vencida]);
        app(AccesoServicios::class)->olvidar();

        $this->actingAs($usuario)->get(route('cliente.paginas.editor'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('servicio.solo_lectura', true)->where('puede.editar', false));
        $this->actingAs($usuario)->putJson(route('cliente.paginas.borrador'), ['documento' => $this->documento([]), 'version' => $pagina->version])->assertForbidden();
    }

    public function test_subida_de_imagenes_validaciones_y_aislamiento(): void
    {
        $negocio = $this->negocioConPaginas();
        $usuario = $this->propietario($negocio);
        $pagina = $this->editor($usuario);

        $id = $this->actingAs($usuario)->post(route('cliente.paginas.medios.store'), ['archivo' => UploadedFile::fake()->image('logo.jpg', 300, 200), 'tipo' => 'imagen'], ['Accept' => 'application/json'])
            ->assertCreated()->json('medio.id');
        $archivo = ArchivoPublico::query()->where('uuid', $id)->firstOrFail();
        $this->assertSame($negocio->id, $archivo->negocio_id);
        $this->assertContains($archivo->extension, ['webp', 'png'], 'Se re-codifica.');

        // Extensión falsa: un texto con nombre .png.
        $this->actingAs($usuario)->post(route('cliente.paginas.medios.store'), ['archivo' => UploadedFile::fake()->createWithContent('foto.png', '<?php echo 1; ?>'), 'tipo' => 'imagen'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('archivo');

        // Demasiado grande.
        config(['dasti.medios.imagen_max_kb' => 1]);
        $this->actingAs($usuario)->post(route('cliente.paginas.medios.store'), ['archivo' => UploadedFile::fake()->image('grande.png', 900, 900), 'tipo' => 'imagen'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('archivo');

        // Imagen de otro negocio: no se puede referenciar ni eliminar.
        $otro = $this->negocioConPaginas();
        $ajena = $this->imagen($otro);
        $this->actingAs($usuario)->putJson(route('cliente.paginas.borrador'), [
            'documento' => $this->documento([], ['perfil' => ['foto' => $ajena->uuid]]),
            'version' => $pagina->version,
        ])->assertStatus(422)->assertJsonValidationErrors('perfil.foto');
        $this->actingAs($usuario)->deleteJson(route('cliente.paginas.medios.destroy', $ajena->uuid))->assertNotFound();

        $guardado = $this->actingAs($usuario)->putJson(route('cliente.paginas.borrador'), [
            'documento' => $this->documento([], ['perfil' => ['foto' => $archivo->uuid]]),
            'version' => $pagina->version,
        ])->assertOk();
        $this->assertSame($archivo->url(), $guardado->json('documento.perfil.foto.url'));
        $this->actingAs($usuario)->getJson(route('cliente.paginas.medios.index'))->assertOk()->assertJsonCount(1, 'medios');
    }

    public function test_mensajes_solo_del_propio_negocio(): void
    {
        $negocio = $this->negocioConPaginas();
        $usuario = $this->propietario($negocio);
        $pagina = $this->editor($usuario);
        $mensaje = MensajePagina::query()->create(['pagina_id' => $pagina->id, 'negocio_id' => $negocio->id, 'nombre' => 'Ana', 'email' => 'ana@correo.mx', 'mensaje' => 'Hola, quiero información']);

        $this->actingAs($usuario)->get(route('cliente.paginas.mensajes.index'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('cliente/paginas/Mensajes')->where('mensajes.data.0.nombre', 'Ana')->where('sin_leer', 1));
        $this->actingAs($usuario)->patch(route('cliente.paginas.mensajes.update', $mensaje), ['leido' => true])->assertRedirect();
        $this->assertNotNull($mensaje->fresh()?->leido_en);

        $ajeno = $this->propietario($this->negocioConPaginas());
        $this->actingAs($ajeno)->get(route('cliente.paginas.mensajes.index'))->assertInertia(fn (Assert $p) => $p->where('mensajes.data', []));
        $this->actingAs($ajeno)->delete(route('cliente.paginas.mensajes.destroy', $mensaje))->assertNotFound();
        $this->assertNotNull($mensaje->fresh());

        $this->actingAs($usuario)->delete(route('cliente.paginas.mensajes.destroy', $mensaje))->assertRedirect();
        $this->assertNull($mensaje->fresh());
    }

    public function test_direccion_disponible_y_qr_descargable(): void
    {
        $negocio = $this->negocioConPaginas();
        $usuario = $this->propietario($negocio);
        $this->editor($usuario);

        $this->actingAs($usuario)->getJson(route('cliente.paginas.direccion.disponible', ['slug' => 'admin']))->assertOk()->assertJsonPath('disponible', false);
        $this->actingAs($usuario)->getJson(route('cliente.paginas.direccion.disponible', ['slug' => 'cafe-aurora-centro']))->assertOk()->assertJsonPath('disponible', true);
        $this->actingAs($usuario)->putJson(route('cliente.paginas.direccion.update'), ['slug' => 'cafe-aurora-centro'])->assertOk()->assertJsonPath('pagina.slug', 'cafe-aurora-centro');

        $png = $this->actingAs($usuario)->get(route('cliente.paginas.qr', ['formato' => 'png', 'descargar' => 1]))->assertOk();
        $this->assertSame('image/png', $png->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="qr-cafe-aurora-centro.png"', (string) $png->headers->get('Content-Disposition'));
        $this->assertStringContainsString('<svg', (string) $this->actingAs($usuario)->get(route('cliente.paginas.qr', ['formato' => 'svg']))->getContent());
    }

    public function test_supervision_de_dasti_sin_mensajes_de_visitantes(): void
    {
        $negocio = $this->negocioConPaginas();
        $pagina = $this->publicar($negocio, $this->documento([$this->enlace()]));
        MensajePagina::query()->create(['pagina_id' => $pagina->id, 'negocio_id' => $negocio->id, 'nombre' => 'Visitante Privado', 'email' => 'privado@correo.mx', 'mensaje' => 'Mensaje confidencial']);
        $admin = User::factory()->create();

        $respuesta = $this->actingAs($admin)->get(route('admin.paginas.index'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('admin/paginas/Index')
                ->where('paginas.data.0.negocio', $negocio->nombre_comercial)
                ->where('paginas.data.0.estado', 'publicado')
                ->where('paginas.data.0.url_publica', $pagina->urlPublica())
                ->where('resumen.publicadas', 1));
        $contenido = (string) $respuesta->getContent();
        $this->assertStringNotContainsString('Visitante Privado', $contenido);
        $this->assertStringNotContainsString('privado@correo.mx', $contenido);
        $this->assertStringNotContainsString('Mensaje confidencial', $contenido);

        // Un cliente no puede entrar a la supervisión.
        $this->actingAs($this->propietario($negocio))->get(route('admin.paginas.index'))->assertForbidden();
    }

    public function test_plantillas_originales_validas(): void
    {
        $negocio = $this->negocioConPaginas();
        $usuario = $this->propietario($negocio);

        $this->actingAs($usuario)->get(route('cliente.paginas.editor'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('cliente/paginas/Editor')
                ->has('plantillas', count(app(PlantillasPagina::class)->todas($negocio)))
                ->where('plantillas.0.clave', 'barra-de-cafe')
                ->where('plantillas.0.documento.perfil.nombre', $negocio->nombre_comercial));

        // Aplicar una plantilla (en el editor) y guardar pasa la validación.
        $pagina = PaginaEnlaces::query()->where('negocio_id', $negocio->id)->firstOrFail();
        $plantilla = collect(app(PlantillasPagina::class)->todas($negocio))->firstWhere('clave', 'tecnologia')['documento'];
        $this->actingAs($usuario)->putJson(route('cliente.paginas.borrador'), ['documento' => $plantilla, 'version' => $pagina->version])
            ->assertOk()->assertJsonPath('documento.tema.fondo.textura', 'cuadricula')->assertJsonPath('documento.tema.disposicion.lienzo', 'panel');
        $this->assertTrue(Str::isUuid((string) app(GestorPaginas::class)->paraNegocio($negocio)->borrador['paginas'][0]['id']));
    }
}
