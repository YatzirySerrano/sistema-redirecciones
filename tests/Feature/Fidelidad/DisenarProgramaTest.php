<?php

namespace Tests\Feature\Fidelidad;

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
use App\Services\Fidelidad\RegistroVisitas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\PreparaFidelidad;
use Tests\TestCase;

/**
 * Editor unificado «Diseñar mi programa»: rutas entregadas por el
 * controlador, publicación y restauración (general y por campaña),
 * compatibilidad con diseños guardados antes del editor por pasos, y
 * garantías de que el editor solo cambia la presentación y de que la página
 * pública no lleva controles de edición.
 */
class DisenarProgramaTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $extra
     */
    private function enviar(string $ruta, User $usuario, array $documento, int $version, ?int $campana = null, array $extra = []): TestResponse
    {
        $metodo = $ruta === 'cliente.fidelidad.diseno.update' ? 'putJson' : 'postJson';

        return $this->actingAs($usuario)->{$metodo}(route($ruta), [
            'campana_id' => $campana, 'version' => $version, 'documento' => $documento, ...$extra,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosPublicos(TestResponse $respuesta): array
    {
        preg_match('#<script type="application/json" id="datos-fidelidad">(.*?)</script>#s', (string) $respuesta->getContent(), $m);
        $this->assertNotEmpty($m, 'La página incrusta los datos de la tarjeta');

        return json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
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
            'campanas' => Campana::query()->with('vigente')->orderBy('id')->get()->toArray(),
        ];
    }

    /**
     * @return array{0: Negocio, 1: User, 2: Campana, 3: Consumidor, 4: string}
     */
    private function escenario(): array
    {
        $negocio = $this->negocioConFidelidad();
        $campana = $this->campana($negocio, ['nombre' => 'Cafés', 'meta_visitas' => 2]);
        $consumidor = $this->consumidor($negocio, ['nombre' => 'Consumidora Real']);
        $codigo = $this->tarjeta($consumidor)->codigo;
        $propietario = $this->propietario($negocio);
        $registro = app(RegistroVisitas::class);
        $registro->registrarVisita($propietario, $consumidor, $campana, $this->uuid());
        $this->travel(1)->days();
        $registro->registrarVisita($propietario, $consumidor->fresh() ?? $consumidor, $campana, $this->uuid());
        $this->travel(1)->days();
        $registro->registrarVisita($propietario, $consumidor->fresh() ?? $consumidor, $campana, $this->uuid());

        return [$negocio, $propietario, $campana, $consumidor, $codigo];
    }

    // ---------------------------------------------------------------

    public function test_el_editor_recibe_todas_sus_rutas_del_controlador(): void
    {
        $negocio = $this->negocioConFidelidad();
        $campana = $this->campana($negocio);

        $this->actingAs($this->propietario($negocio))->get(route('cliente.fidelidad.diseno.edit', ['campana' => $campana->id]))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('cliente/fidelidad/Diseno')
                ->where('variante.campana_id', $campana->id)
                ->where('rutas.editar', '/panel/fidelidad/diseno')
                ->where('rutas.editar_campana', '/panel/fidelidad/diseno?campana=__ID__')
                ->where('rutas.guardar', '/panel/fidelidad/diseno')
                ->where('rutas.publicar', '/panel/fidelidad/diseno/publicacion')
                ->where('rutas.restaurar', '/panel/fidelidad/diseno/versiones/__ID__/restauracion')
                ->where('rutas.quitar_variante', '/panel/fidelidad/diseno/variantes/__ID__')
                ->where('rutas.subir_imagen', '/panel/fidelidad/diseno/imagenes')
                ->where('rutas.inicio', '/panel/fidelidad')
                ->has('rutas.campanas'));
    }

    public function test_publicar_y_restaurar_con_diseno_general_y_de_campana(): void
    {
        [$negocio, $propietario, $campana, , $codigo] = $this->escenario();

        // Diseño general: dos publicaciones.
        $this->enviar('cliente.fidelidad.diseno.publicar', $propietario, $this->documento(['programa' => ['nombre' => 'General uno'], 'tarjeta' => ['decoracion' => 'ondas']]), 0)->assertOk();
        $this->enviar('cliente.fidelidad.diseno.publicar', $propietario, $this->documento(['programa' => ['nombre' => 'General dos'], 'tarjeta' => ['decoracion' => 'confeti']]), 1)->assertOk();

        // Diseño para la campaña: una publicación y un borrador.
        $this->enviar('cliente.fidelidad.diseno.publicar', $propietario, $this->documento(['programa' => ['nombre' => 'Campaña uno']]), 0, $campana->id)->assertOk();
        $this->enviar('cliente.fidelidad.diseno.update', $propietario, $this->documento(['programa' => ['nombre' => 'Campaña borrador']]), 1, $campana->id)->assertOk();

        $general = Diseno::query()->where('campana_clave', 0)->firstOrFail();
        $variante = Diseno::query()->where('campana_clave', $campana->id)->firstOrFail();
        $primeraGeneral = DisenoVersion::query()->where('diseno_id', $general->id)->orderBy('id')->firstOrFail();

        // Restaurar la primera versión general: va al borrador, no se publica.
        $this->actingAs($propietario)->postJson(route('cliente.fidelidad.diseno.restaurar', $primeraGeneral), ['version' => 2])->assertOk()
            ->assertJsonPath('diseno.borrador.programa.nombre', 'General uno')
            ->assertJsonPath('diseno.borrador.tarjeta.decoracion', 'ondas')
            ->assertJsonPath('diseno.publicado.programa.nombre', 'General dos')
            ->assertJsonPath('diseno.cambios_sin_publicar', true);

        $datos = $this->datosPublicos($this->get(route('fidelidad.publico.credencial', $codigo)));
        $this->assertSame('General dos', $datos['disenos']['general']['programa']['nombre']);
        $this->assertSame('Campaña uno', $datos['disenos']['variantes'][0]['programa']['nombre']);

        // Publicar lo restaurado: ahora sí lo ven los clientes.
        $restaurado = Diseno::query()->findOrFail($general->id);
        $this->enviar('cliente.fidelidad.diseno.publicar', $propietario, $restaurado->borrador, $restaurado->version)->assertOk();
        $datos = $this->datosPublicos($this->get(route('fidelidad.publico.credencial', $codigo)));
        $this->assertSame('General uno', $datos['disenos']['general']['programa']['nombre']);
        $this->assertSame('ondas', $datos['disenos']['general']['tarjeta']['decoracion']);

        // El editor de la campaña muestra su borrador, su publicación y el general.
        $this->actingAs($propietario)->get(route('cliente.fidelidad.diseno.edit', ['campana' => $campana->id]))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('diseno.borrador.programa.nombre', 'Campaña borrador')
                ->where('diseno.publicado.programa.nombre', 'Campaña uno')
                ->where('general.programa.nombre', 'General uno')
                ->has('versiones', 1));

        // Restaurar la versión de la campaña solo afecta a esa campaña.
        $primeraVariante = DisenoVersion::query()->where('diseno_id', $variante->id)->orderBy('id')->firstOrFail();
        $this->actingAs($propietario)->postJson(route('cliente.fidelidad.diseno.restaurar', $primeraVariante), ['version' => 2])->assertOk()
            ->assertJsonPath('diseno.borrador.programa.nombre', 'Campaña uno');
        $this->assertSame('General uno', Diseno::query()->findOrFail($general->id)->borrador['programa']['nombre']);
    }

    public function test_un_diseno_guardado_antes_del_editor_por_pasos_se_ve_igual(): void
    {
        [$negocio, $propietario, , , $codigo] = $this->escenario();

        // Documento tal como lo guardaba el editor anterior (esquema 1).
        $antiguo = $this->documento([
            'plantilla' => 'cafeteria',
            'programa' => ['nombre' => 'Club antiguo', 'lema' => 'Desde siempre'],
            'pagina' => ['fondo_tipo' => 'solido', 'color1' => '#fef3c7', 'decoracion' => 'granos'],
            'tarjeta' => ['decoracion' => 'hojas', 'decoracion_opacidad' => 22, 'orientacion' => 'horizontal'],
            'progreso' => ['presentacion' => 'ruta', 'icono' => 'cafe'],
            'contenido' => ['secciones' => [['titulo' => 'Horario', 'elementos' => ['Lunes a viernes']]], 'terminos' => 'Una visita por día.'],
        ]);
        Diseno::query()->forceCreate([
            'negocio_id' => $negocio->id, 'campana_id' => null, 'campana_clave' => 0,
            'borrador' => $antiguo, 'publicado' => $antiguo, 'publicado_en' => now(), 'version' => 3,
        ]);

        // La página pública lo muestra sin cambios.
        $this->assertSame($antiguo, $this->datosPublicos($this->get(route('fidelidad.publico.credencial', $codigo)))['disenos']['general']);

        // El editor lo abre tal cual y sin cambios pendientes.
        $this->actingAs($propietario)->get(route('cliente.fidelidad.diseno.edit'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('diseno.borrador', $antiguo)
                ->where('diseno.publicado', $antiguo)
                ->where('diseno.cambios_sin_publicar', false));

        // Volver a publicarlo desde el nuevo editor produce el mismo documento.
        $this->enviar('cliente.fidelidad.diseno.publicar', $propietario, $antiguo, 3)->assertOk()
            ->assertJsonPath('avisos', []);
        $this->assertSame($antiguo, Diseno::query()->firstOrFail()->publicado);
        $this->assertSame($antiguo, $this->datosPublicos($this->get(route('fidelidad.publico.credencial', $codigo)))['disenos']['general']);
    }

    public function test_guardar_y_publicar_no_modifica_visitas_metas_recompensas_canjes_ni_movimientos(): void
    {
        [, $propietario, $campana, $consumidor, $codigo] = $this->escenario();
        $antes = $this->instantaneaOperacion();
        $paginaAntes = $this->datosPublicos($this->get(route('fidelidad.publico.credencial', $codigo)))['programas'];

        // Intentos de colar datos de operación en el documento y en la petición.
        $intrusos = [
            'visitas' => 99, 'meta' => 1, 'recompensa' => 'Todo gratis', 'consumidor' => ['nombre' => 'Otro'],
            'progreso' => ['visitas' => 50, 'meta_visitas' => 1],
            'contenido' => ['recompensas' => [['descripcion' => 'Falsa']], 'canjes' => 5],
            'programas' => [['campana' => 'X', 'visitas' => 100]],
        ];
        $extra = ['visitas' => 99, 'consumidor_id' => $consumidor->id, 'meta_visitas' => 1, 'movimientos' => [['tipo' => 'visita']]];

        $this->enviar('cliente.fidelidad.diseno.update', $propietario, $this->documento($intrusos), 0, null, $extra)->assertOk();
        $this->enviar('cliente.fidelidad.diseno.publicar', $propietario, $this->documento($intrusos), 1, null, $extra)->assertOk();
        $this->enviar('cliente.fidelidad.diseno.publicar', $propietario, $this->documento($intrusos), 0, $campana->id, $extra)->assertOk();

        $this->assertEquals($antes, $this->instantaneaOperacion());
        // El progreso público es idéntico; solo cambia qué diseño usa la campaña.
        $sinDiseno = fn (array $programas) => array_map(fn (array $p) => array_diff_key($p, ['variante' => true]), $programas);
        $paginaDespues = $this->datosPublicos($this->get(route('fidelidad.publico.credencial', $codigo)))['programas'];
        $this->assertSame($sinDiseno($paginaAntes), $sinDiseno($paginaDespues));
        $this->assertSame([1, 2, 'disponible'], [$paginaDespues[0]['visitas'], $paginaDespues[0]['meta'], $paginaDespues[0]['estado']]);

        foreach (Diseno::query()->get() as $diseno) {
            foreach ([$diseno->borrador, $diseno->publicado] as $guardado) {
                // Mismas claves que el documento predeterminado: nada más.
                $this->assertSame(array_keys(DocumentoDiseno::predeterminado()), array_keys($guardado));
                $this->assertSame(array_keys(DocumentoDiseno::predeterminado()['progreso']), array_keys($guardado['progreso']));
                $this->assertSame(array_keys(DocumentoDiseno::predeterminado()['contenido']), array_keys($guardado['contenido']));
                $json = (string) json_encode($guardado);
                $this->assertStringNotContainsString('Todo gratis', $json);
                $this->assertStringNotContainsString('Falsa', $json);
                $this->assertStringNotContainsString('visitas":', $json);
            }
        }
    }

    public function test_la_pagina_publica_no_incluye_controles_ni_datos_del_editor(): void
    {
        [, $propietario, $campana, , $codigo] = $this->escenario();
        $this->enviar('cliente.fidelidad.diseno.publicar', $propietario, $this->documento(['programa' => ['nombre' => 'Club público']]), 0)->assertOk();
        $this->enviar('cliente.fidelidad.diseno.publicar', $propietario, $this->documento(), 0, $campana->id)->assertOk();

        $respuesta = $this->get(route('fidelidad.publico.credencial', $codigo))->assertOk();
        $html = (string) $respuesta->getContent();

        foreach (['data-zona', 'zona-editable', 'Pulsa Intro para editar', 'Diseñar mi programa', '/panel/fidelidad', '__ID__'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $html, "La página pública no debe contener «{$prohibido}»");
        }

        $datos = $this->datosPublicos($respuesta);
        foreach (['rutas', 'edicion', 'versiones', 'campanas', 'plantillas', 'archivos', 'borrador'] as $clave) {
            $this->assertArrayNotHasKey($clave, $datos);
        }
        $this->assertSame('Club público', $datos['disenos']['general']['programa']['nombre']);
    }

    public function test_la_vista_previa_del_editor_no_recibe_datos_personales_reales(): void
    {
        [, $propietario] = $this->escenario();

        $contenido = (string) $this->actingAs($propietario)->get(route('cliente.fidelidad.diseno.edit'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Consumidora Real', $contenido);
        $this->assertStringNotContainsString('Consumidora', $contenido);
    }
}
