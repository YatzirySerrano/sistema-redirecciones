<?php

namespace Tests\Feature\Admin;

use App\Models\Configuracion;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\User;
use App\Services\Apariencia\Color;
use App\Services\Apariencia\TemaVisual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PersonalizacionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function colores(array $cambios = []): array
    {
        return [...TemaVisual::PREDETERMINADOS, ...$cambios];
    }

    public function test_sin_personalizar_se_usa_la_apariencia_original(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.personalizacion.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/configuracion/Apariencia')
                ->where('personalizado', false)
                ->where('colores', TemaVisual::PREDETERMINADOS)
                ->where('tema', null));

        $this->assertSame([], TemaVisual::problemasDeContraste(TemaVisual::PREDETERMINADOS));
    }

    public function test_guardar_colores_persiste_en_mariadb_audita_y_se_aplica_en_toda_la_plataforma(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->put(route('admin.personalizacion.update'), $this->colores(['principal' => '#0F766E', 'botones' => '#0f766e']))
            ->assertSessionHasNoErrors();

        $guardado = Configuracion::query()->where('clave', TemaVisual::CLAVE)->value('valor');
        $this->assertSame('#0f766e', $guardado['principal']);
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'apariencia.actualizada']);

        // Variables CSS en el HTML inicial (sin recompilar) y en la prop compartida.
        foreach ([route('admin.negocios.index'), route('admin.productos.index'), route('admin.personalizacion.edit')] as $url) {
            $this->actingAs($admin)->get($url)->assertOk()->assertSee('--primary:#0f766e', false);
        }

        $this->actingAs($admin)->get(route('admin.panel'))
            ->assertInertia(fn (Assert $page) => $page->where('tema.claro.--boton', '#0f766e'));
    }

    public function test_la_configuracion_se_conserva_entre_sesiones_y_usuarios(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin)->put(route('admin.personalizacion.update'), $this->colores(['navegacion' => '#1e293b']));
        auth()->logout();

        $cliente = User::factory()->cliente()->create();
        $this->actingAs($cliente)->get(route('cliente.inicio'))->assertOk()->assertSee('--sidebar:#1e293b', false);

        // Página de inicio de sesión (sin sesión) también.
        auth()->logout();
        $this->get(route('login'))->assertOk()->assertSee('--sidebar:#1e293b', false);
    }

    public function test_restablecer_vuelve_a_la_apariencia_original(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin)->put(route('admin.personalizacion.update'), $this->colores(['principal' => '#0f766e']));

        $this->actingAs($admin)->delete(route('admin.personalizacion.destroy'))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('configuraciones', ['clave' => TemaVisual::CLAVE]);
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'apariencia.restablecida']);
        $this->actingAs($admin)->get(route('admin.panel'))
            ->assertDontSee('--primary:#0f766e', false)
            ->assertInertia(fn (Assert $page) => $page->where('tema', null));
    }

    public function test_rechaza_combinaciones_ilegibles(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->put(route('admin.personalizacion.update'), $this->colores(['fondo' => '#222222']))
            ->assertSessionHasErrors('fondo');
        $this->actingAs($admin)->put(route('admin.personalizacion.update'), $this->colores(['enlaces' => '#f0f0f0']))
            ->assertSessionHasErrors('enlaces');
        $this->actingAs($admin)->put(route('admin.personalizacion.update'), $this->colores(['botones' => '#fafafa']))
            ->assertSessionHasErrors('botones');
        $this->actingAs($admin)->put(route('admin.personalizacion.update'), $this->colores(['principal' => 'rojo']))
            ->assertSessionHasErrors('principal');
        $this->actingAs($admin)->put(route('admin.personalizacion.update'), $this->colores(['principal' => '#ff0000"><script>']))
            ->assertSessionHasErrors('principal');

        $this->assertDatabaseMissing('configuraciones', ['clave' => TemaVisual::CLAVE]);
    }

    public function test_el_texto_calculado_siempre_es_legible(): void
    {
        foreach (['#ffff00', '#000080', '#808080', '#0f766e', '#f472b6'] as $color) {
            $tokens = TemaVisual::calcularTokens($this->colores(['principal' => $color, 'botones' => $color, 'navegacion' => $color]));

            $this->assertGreaterThanOrEqual(4.5, Color::contraste($tokens['claro']['--primary'], $tokens['claro']['--primary-foreground']));
            $this->assertGreaterThanOrEqual(4.5, Color::contraste($tokens['claro']['--sidebar'], $tokens['claro']['--sidebar-accent-foreground']));
            $this->assertGreaterThanOrEqual(4.5, Color::contraste($tokens['oscuro']['--enlace'], '#0f0d17'));
        }
    }

    public function test_requiere_permisos(): void
    {
        $lectura = new Rol;
        $lectura->forceFill(['name' => 'Ve apariencia', 'guard_name' => 'web', 'ambito' => 'plataforma'])->save();
        $lectura->syncPermissions(['personalizacion.ver']);
        $soloVer = User::factory()->conRol($lectura)->create();

        $this->actingAs($soloVer)->get(route('admin.personalizacion.edit'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('puede_editar', false));
        $this->actingAs($soloVer)->put(route('admin.personalizacion.update'), $this->colores(['principal' => '#0f766e']))->assertForbidden();
        $this->actingAs($soloVer)->delete(route('admin.personalizacion.destroy'))->assertForbidden();

        $cliente = User::factory()->cliente()->create();
        $this->actingAs($cliente)->get(route('admin.personalizacion.edit'))->assertForbidden();

        $this->assertDatabaseMissing('configuraciones', ['clave' => TemaVisual::CLAVE]);
    }

    public function test_no_modifica_los_colores_de_los_qr(): void
    {
        $admin = User::factory()->create();
        $producto = Producto::factory()->create(['opciones_qr' => ['color' => '#111111', 'fondo' => '#ffffff']]);

        $this->actingAs($admin)->put(route('admin.personalizacion.update'), $this->colores(['principal' => '#0f766e']));

        $this->assertSame('#111111', $producto->fresh()?->opciones_qr['color'] ?? null);
        $svg = $this->actingAs($admin)->get(route('productos.qr.show', [$producto, 'formato' => 'svg']))->assertOk()->getContent();
        $this->assertStringNotContainsString('0f766e', strtolower((string) $svg));
    }

    /**
     * Defecto corregido en la fase 4: la hoja de app.css se cargaba después
     * de la personalizada y la anulaba (misma especificidad). La etiqueta va
     * después de @vite y usa selectores más específicos que `:root`/`.dark`.
     */
    public function test_la_hoja_personalizada_gana_a_app_css_sin_depender_del_orden(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin)->put(route('admin.personalizacion.update'), $this->colores(['principal' => '#0f766e']));

        $html = (string) $this->actingAs($admin)->get(route('admin.panel'))->getContent();

        $this->assertStringContainsString('html:root:not(.dark){', $html);
        $this->assertStringContainsString('html:root.dark{', $html);

        $posTema = strpos($html, 'id="tema-personalizado"');
        $posCss = strpos($html, 'app.css');
        $this->assertNotFalse($posTema);
        $this->assertNotFalse($posCss);
        $this->assertGreaterThan($posCss, $posTema, 'El tema debe declararse después de app.css.');
    }

    public function test_el_modo_oscuro_solo_recibe_colores_de_marca_con_contraste(): void
    {
        $tokens = TemaVisual::calcularTokens($this->colores(['principal' => '#1e3a8a', 'enlaces' => '#1e3a8a', 'botones' => '#1e3a8a']));

        $this->assertArrayNotHasKey('--background', $tokens['oscuro']);
        $this->assertArrayNotHasKey('--sidebar', $tokens['oscuro']);
        $this->assertGreaterThanOrEqual(4.5, Color::contraste($tokens['oscuro']['--primary'], '#0f0d17'));
    }
}
