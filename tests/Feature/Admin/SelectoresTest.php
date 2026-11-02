<?php

namespace Tests\Feature\Admin;

use App\Models\Negocio;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Selectores con búsqueda remota (negocios, usuarios, productos).
 */
class SelectoresTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function etiquetas(string $url): array
    {
        return collect($this->getJson($url)->assertOk()->json('data'))->pluck('label')->all();
    }

    public function test_busca_negocios_por_nombre_parcial_sin_importar_mayusculas_ni_acentos(): void
    {
        $admin = User::factory()->create();
        Negocio::factory()->create(['nombre_comercial' => 'Punto Polar Hielo y Agua']);
        Negocio::factory()->create(['nombre_comercial' => 'Café La Esquina']);
        Negocio::factory()->create(['nombre_comercial' => 'Taller Mecánico Ruiz']);
        $this->actingAs($admin);

        $this->assertSame(['Punto Polar Hielo y Agua'], $this->etiquetas(route('admin.opciones.negocios', ['buscar' => 'polar'])));
        $this->assertSame(['Punto Polar Hielo y Agua'], $this->etiquetas(route('admin.opciones.negocios', ['buscar' => 'AGUA'])));
        $this->assertSame(['Café La Esquina'], $this->etiquetas(route('admin.opciones.negocios', ['buscar' => 'cafe'])));
        $this->assertSame(['Taller Mecánico Ruiz'], $this->etiquetas(route('admin.opciones.negocios', ['buscar' => 'mecanico'])));
    }

    public function test_busca_negocios_por_otras_referencias(): void
    {
        $admin = User::factory()->create();
        Negocio::factory()->create(['nombre_comercial' => 'Florería Margarita', 'responsable' => 'Lucía Pérez', 'email_contacto' => 'ventas@margarita.mx']);

        $this->actingAs($admin);
        $this->assertSame(['Florería Margarita'], $this->etiquetas(route('admin.opciones.negocios', ['buscar' => 'lucia'])));
        $this->assertSame(['Florería Margarita'], $this->etiquetas(route('admin.opciones.negocios', ['buscar' => 'margarita.mx'])));
    }

    public function test_sin_coincidencias_devuelve_una_lista_vacia(): void
    {
        $admin = User::factory()->create();
        Negocio::factory()->count(3)->create();

        $this->actingAs($admin)->getJson(route('admin.opciones.negocios', ['buscar' => 'zzz-inexistente']))
            ->assertOk()
            ->assertExactJson(['data' => [], 'hay_mas' => false]);
    }

    public function test_catalogos_grandes_se_paginan_sin_enviar_todo(): void
    {
        $admin = User::factory()->create();
        Negocio::factory()->count(45)->sequence(fn ($s) => ['nombre_comercial' => sprintf('Negocio %03d', $s->index + 1)])->create();
        $this->actingAs($admin);

        DB::enableQueryLog();
        $pagina1 = $this->getJson(route('admin.opciones.negocios'))->assertOk();
        $consulta = collect(DB::getQueryLog())->first(fn ($q) => str_contains($q['query'], 'from `negocios`'));
        $this->assertStringContainsString('limit 21', (string) $consulta['query']);

        $this->assertCount(20, $pagina1->json('data'));
        $this->assertTrue($pagina1->json('hay_mas'));
        $this->assertSame('Negocio 001', $pagina1->json('data.0.label'));

        $pagina3 = $this->getJson(route('admin.opciones.negocios', ['pagina' => 3]))->assertOk();
        $this->assertCount(5, $pagina3->json('data'));
        $this->assertFalse($pagina3->json('hay_mas'));
        $this->assertSame('Negocio 041', $pagina3->json('data.0.label'));
    }

    public function test_solo_activos_excluye_negocios_suspendidos(): void
    {
        $admin = User::factory()->create();
        Negocio::factory()->create(['nombre_comercial' => 'Activo Uno']);
        Negocio::factory()->suspendido()->create(['nombre_comercial' => 'Suspendido Dos']);
        $this->actingAs($admin);

        $this->assertSame(['Activo Uno', 'Suspendido Dos (suspendido)'], $this->etiquetas(route('admin.opciones.negocios')));
        $this->assertSame(['Activo Uno'], $this->etiquetas(route('admin.opciones.negocios', ['solo_activos' => 1])));
    }

    public function test_busca_usuarios_por_nombre_o_correo(): void
    {
        $admin = User::factory()->create(['name' => 'Zoe Admin']);
        User::factory()->cliente()->create(['name' => 'Óscar Núñez', 'email' => 'oscar@cliente.test']);
        $this->actingAs($admin);

        $this->assertSame(['Óscar Núñez'], $this->etiquetas(route('admin.opciones.usuarios', ['buscar' => 'oscar nunez'])));
        $this->assertSame(['Óscar Núñez'], $this->etiquetas(route('admin.opciones.usuarios', ['buscar' => 'cliente.test'])));
    }

    public function test_busca_productos_de_un_negocio(): void
    {
        $admin = User::factory()->create();
        $negocio = Negocio::factory()->create();
        Producto::factory()->for($negocio)->create(['nombre' => 'Tarjeta mostrador']);
        Producto::factory()->create(['nombre' => 'Tarjeta ajena']);
        $this->actingAs($admin);

        $this->assertSame(['Tarjeta mostrador'], $this->etiquetas(route('admin.opciones.productos', ['negocio_id' => $negocio->id, 'buscar' => 'tarjeta'])));
    }

    public function test_los_selectores_respetan_permisos(): void
    {
        $cliente = User::factory()->cliente()->create();
        $this->actingAs($cliente)->getJson(route('admin.opciones.negocios'))->assertForbidden();

        $sinCatalogos = new Rol;
        $sinCatalogos->forceFill(['name' => 'Solo apariencia', 'guard_name' => 'web', 'ambito' => 'plataforma'])->save();
        $sinCatalogos->syncPermissions(['personalizacion.ver']);
        $usuario = User::factory()->conRol($sinCatalogos)->create();

        $this->actingAs($usuario)->getJson(route('admin.opciones.negocios'))->assertForbidden();
        $this->actingAs($usuario)->getJson(route('admin.opciones.usuarios'))->assertForbidden();

        auth()->logout();
        $this->getJson(route('admin.opciones.negocios'))->assertUnauthorized();
    }

    public function test_valida_los_parametros_de_busqueda(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->getJson(route('admin.opciones.negocios', ['pagina' => 0]))->assertUnprocessable();
        $this->actingAs($admin)->getJson(route('admin.opciones.negocios', ['buscar' => str_repeat('a', 101)]))->assertUnprocessable();
    }

    public function test_la_seleccion_se_conserva_en_filtros_y_formularios(): void
    {
        $admin = User::factory()->create();
        $negocio = Negocio::factory()->create(['nombre_comercial' => 'Punto Polar Hielo y Agua']);

        $this->actingAs($admin)->get(route('admin.productos.index', ['negocio_id' => $negocio->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filtros.negocio_id', (string) $negocio->id)
                ->where('negocio_filtro.label', 'Punto Polar Hielo y Agua'));

        $this->actingAs($admin)->get(route('admin.productos.create', ['negocio_id' => $negocio->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('negocio_inicial.value', (string) $negocio->id)
                ->missing('negocios'));

        $this->actingAs($admin)->get(route('admin.usuarios.create', ['negocio_id' => $negocio->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('negocio_inicial.label', 'Punto Polar Hielo y Agua')
                ->missing('negocios'));
    }

    public function test_los_formularios_no_cargan_catalogos_completos_de_negocios(): void
    {
        $admin = User::factory()->create();
        Negocio::factory()->count(30)->create();
        $producto = Producto::factory()->create();

        foreach ([
            route('admin.productos.show', $producto),
            route('admin.enlaces.index'),
            route('admin.auditoria.index'),
            route('admin.estadisticas.index'),
            route('admin.usuarios.index'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk()
                ->assertInertia(fn (Assert $page) => $page->missing('negocios')->missing('usuarios.0'));
        }
    }
}
