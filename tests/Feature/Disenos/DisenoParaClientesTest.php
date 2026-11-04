<?php

namespace Tests\Feature\Disenos;

use App\Enums\EstadoPagina;
use App\Models\Fidelidad\Diseno;
use App\Models\Negocio;
use App\Models\Paginas\PaginaEnlaces;
use App\Models\Paginas\VersionPagina;
use App\Models\RegistroAuditoria;
use App\Models\Rol;
use App\Models\User;
use App\Services\Autorizacion\CatalogoPermisos;
use App\Services\Fidelidad\Diseno\DocumentoDiseno;
use App\Services\Paginas\GestorPaginas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\PreparaFidelidad;
use Tests\Concerns\PreparaPaginas;
use Tests\TestCase;

/**
 * «Diseño para clientes»: el personal DASTI autorizado abre los editores de
 * un negocio elegido. La autorización vive en el servidor (permiso +
 * contratación), los cambios se guardan en ESE negocio, la auditoría
 * registra al autor real y nadie de un negocio puede elegir otro.
 */
class DisenoParaClientesTest extends TestCase
{
    use PreparaFidelidad, PreparaPaginas, RefreshDatabase {
        PreparaPaginas::propietario insteadof PreparaFidelidad;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    private function adminConPermisos(array $permisos): User
    {
        $rol = new Rol;
        $rol->forceFill(['name' => 'Diseño '.uniqid(), 'guard_name' => 'web', 'ambito' => CatalogoPermisos::AMBITO_PLATAFORMA])->save();
        $rol->syncPermissions($permisos);

        return User::factory()->administrador()->conRol($rol)->create();
    }

    public function test_la_pantalla_de_seleccion_muestra_el_estado_de_cada_servicio(): void
    {
        $admin = User::factory()->create();
        $negocio = $this->negocioConPaginas();

        $this->actingAs($admin)->get(route('admin.disenos.index', ['negocio' => $negocio->id]))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('admin/disenos/Index')
                ->where('negocio.id', $negocio->id)
                ->where('modulos.paginas.puede_abrir', true)
                ->where('modulos.paginas.puede_guardar', true)
                ->where('modulos.paginas.url', route('admin.disenos.paginas.editor', $negocio, false))
                ->where('modulos.fidelidad.puede_abrir', false)
                ->where('modulos.fidelidad.url', null));

        // Sin permisos de diseño no hay acceso a la pantalla.
        $otro = $this->adminConPermisos(['panel.ver', 'negocios.ver']);
        $this->actingAs($otro)->get(route('admin.disenos.index'))->assertForbidden();
    }

    public function test_dasti_edita_y_publica_la_pagina_de_un_negocio_con_auditoria(): void
    {
        $admin = User::factory()->create();
        $negocio = $this->negocioConPaginas();
        $ajeno = $this->negocioConPaginas();
        $paginaAjena = app(GestorPaginas::class)->paraNegocio($ajeno);

        $this->actingAs($admin)->get(route('admin.disenos.paginas.editor', $negocio))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('cliente/paginas/Editor')
                ->where('contexto_dasti.negocio_id', $negocio->id)
                ->where('puede.editar', true)
                ->where('puede.publicar', true)
                ->where('puede.mensajes', false)
                ->where('rutas.borrador', route('admin.disenos.paginas.borrador', $negocio, false))
                ->where('rutas.direccion_update', null)
                ->where('rutas.dominios', null));
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'diseno.acceso_dasti', 'negocio_id' => $negocio->id, 'user_id' => $admin->id]);

        $pagina = app(GestorPaginas::class)->paraNegocio($negocio);
        $this->actingAs($admin)->putJson(route('admin.disenos.paginas.borrador', $negocio), [
            'documento' => $this->documento([$this->enlace('Menú', 'https://menu.ejemplo.mx')]),
            'version' => $pagina->version,
        ])->assertOk();

        $pagina->refresh();
        $this->actingAs($admin)->postJson(route('admin.disenos.paginas.publicar', $negocio), ['version' => $pagina->version])->assertOk();

        $this->assertSame(EstadoPagina::Publicada, $pagina->fresh()?->estado);
        $this->assertSame('Menú', $pagina->fresh()?->publicado['paginas'][0]['bloques'][0]['titulo'] ?? null);
        // El otro negocio no cambia.
        $this->assertSame($paginaAjena->version, $paginaAjena->fresh()?->version);

        $registro = RegistroAuditoria::query()->where('negocio_id', $negocio->id)->where('accion', 'like', 'pagina%')->latest('id')->firstOrFail();
        $this->assertSame($admin->id, $registro->user_id);
        $this->assertTrue($registro->valores_nuevos['realizado_por_dasti'] ?? false);
        $this->assertStringContainsString('(personal DASTI)', $registro->descripcion);
    }

    public function test_no_se_pueden_tocar_versiones_de_otro_negocio_desde_la_ruta_administrativa(): void
    {
        $admin = User::factory()->create();
        $negocio = $this->negocioConPaginas();
        $ajeno = $this->negocioConPaginas();
        $paginaAjena = $this->publicar($ajeno, $this->documento([$this->enlace()]));
        $version = VersionPagina::query()->where('pagina_id', $paginaAjena->id)->firstOrFail();

        $this->actingAs($admin)->getJson(route('admin.disenos.paginas.versiones.show', [$negocio, $version]))->assertNotFound();
        $this->actingAs($admin)->postJson(route('admin.disenos.paginas.versiones.restaurar', [$negocio, $version]), ['version' => 1])->assertNotFound();
    }

    public function test_un_permiso_no_sustituye_la_contratacion(): void
    {
        $admin = User::factory()->create();
        $sinServicio = Negocio::factory()->create();

        $this->actingAs($admin)->get(route('admin.disenos.paginas.editor', $sinServicio))
            ->assertRedirect(route('admin.disenos.index'));
        $this->actingAs($admin)->putJson(route('admin.disenos.paginas.borrador', $sinServicio), ['documento' => [], 'version' => 1])
            ->assertForbidden();
        $this->actingAs($admin)->get(route('admin.disenos.fidelidad.edit', $sinServicio))
            ->assertRedirect(route('admin.disenos.index'));
        $this->assertNull(PaginaEnlaces::query()->where('negocio_id', $sinServicio->id)->first());
    }

    public function test_exige_el_permiso_de_cada_modulo_en_el_servidor(): void
    {
        $negocio = $this->negocioConFidelidad($this->negocioConPaginas());
        $soloFidelidad = $this->adminConPermisos(['panel.ver', 'fidelidad.disenar_negocios']);

        $this->actingAs($soloFidelidad)->get(route('admin.disenos.fidelidad.edit', $negocio))->assertOk();
        $this->actingAs($soloFidelidad)->get(route('admin.disenos.paginas.editor', $negocio))->assertForbidden();
        $this->actingAs($soloFidelidad)->putJson(route('admin.disenos.paginas.borrador', $negocio), ['documento' => [], 'version' => 1])->assertForbidden();
    }

    public function test_un_usuario_de_negocio_no_puede_elegir_otro_negocio(): void
    {
        $propio = $this->negocioConPaginas();
        $ajeno = $this->negocioConPaginas();
        $cliente = $this->propietario($propio);

        $this->actingAs($cliente)->get(route('admin.disenos.paginas.editor', $ajeno))->assertForbidden();
        $this->actingAs($cliente)->putJson(route('admin.disenos.paginas.borrador', $ajeno), ['documento' => [], 'version' => 1])->assertForbidden();
        $this->actingAs($cliente)->get(route('admin.disenos.index'))->assertForbidden();

        // En su propio editor solo existe su negocio, aunque intente pasar otro.
        $this->actingAs($cliente)->get(route('cliente.paginas.editor', ['negocio' => $ajeno->id]))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('contexto_dasti', null)
                ->where('rutas.borrador', route('cliente.paginas.borrador', absolute: false)));
    }

    public function test_dasti_publica_el_diseno_de_fidelidad_de_un_negocio(): void
    {
        $admin = User::factory()->create();
        $negocio = $this->negocioConFidelidad();
        $campana = $this->campana($negocio);
        $consumidor = $this->consumidor($negocio);

        $this->actingAs($admin)->get(route('admin.disenos.fidelidad.edit', $negocio))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('cliente/fidelidad/Diseno')
                ->where('contexto_dasti.negocio_id', $negocio->id)
                ->where('edicion.puede', true)
                ->where('rutas.publicar', route('admin.disenos.fidelidad.publicar', $negocio, false))
                ->where('rutas.restaurar', route('admin.disenos.fidelidad.restaurar', [$negocio, '__ID__'], false)));

        $documento = DocumentoDiseno::predeterminado();
        $this->actingAs($admin)->postJson(route('admin.disenos.fidelidad.publicar', $negocio), [
            'campana_id' => null, 'version' => 0, 'documento' => $documento,
        ])->assertOk();

        $this->assertNotNull(Diseno::query()->where('negocio_id', $negocio->id)->firstOrFail()->publicado);
        $this->assertSame($campana->fresh()?->updated_at?->toIso8601String(), $campana->updated_at?->toIso8601String());
        $this->assertSame(0, $consumidor->participaciones()->count());

        $registro = RegistroAuditoria::query()->where('negocio_id', $negocio->id)->where('accion', 'like', 'fidelidad.diseno%')->latest('id')->first();
        $this->assertNotNull($registro);
        $this->assertSame($admin->id, $registro->user_id);
        $this->assertTrue($registro->valores_nuevos['realizado_por_dasti'] ?? false);
    }
}
