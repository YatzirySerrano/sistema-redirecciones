<?php

namespace Tests\Feature\Admin;

use App\Models\Configuracion;
use App\Models\Producto;
use App\Models\RegistroAuditoria;
use App\Models\User;
use App\Services\Configuracion\AjustesPlataforma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConfiguracionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return [
            'permitir_destinos_http' => false,
            'clientes_descargan_qr' => true,
            'clientes_ven_estadisticas' => true,
            'dominios_bloqueados' => "bit.ly\ntinyurl.com, is.gd t.co cutt.ly shorturl.at",
            'dias_retencion_eventos' => '395',
            ...$cambios,
        ];
    }

    public function test_guardar_persiste_audita_solo_lo_que_cambia_y_se_aplica(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->put(route('admin.configuracion.update'), $this->datos())->assertSessionHasNoErrors();
        $this->assertSame(0, Configuracion::query()->count(), 'Guardar los mismos valores no debe crear registros.');
        $this->assertDatabaseMissing('registros_auditoria', ['accion' => 'configuracion.actualizada']);

        $this->actingAs($admin)->put(route('admin.configuracion.update'), $this->datos([
            'permitir_destinos_http' => true,
            'dominios_bloqueados' => 'bit.ly, ACORTADOR.example',
            'dias_retencion_eventos' => '90',
        ]))->assertSessionHasNoErrors();

        $ajustes = app(AjustesPlataforma::class);
        $this->assertTrue($ajustes->booleano('permitir_destinos_http'));
        $this->assertSame(['bit.ly', 'acortador.example'], $ajustes->dominiosBloqueados());
        $this->assertSame(90, $ajustes->obtener('dias_retencion_eventos'));

        $registro = RegistroAuditoria::query()->where('accion', 'configuracion.actualizada')->sole();
        $this->assertEqualsCanonicalizing(['permitir_destinos_http', 'dominios_bloqueados', 'dias_retencion_eventos'], array_keys($registro->valores_nuevos));

        // La nueva regla se aplica de inmediato al validar destinos.
        $producto = Producto::factory()->create();
        $this->actingAs($admin)->put(route('admin.productos.destino', $producto), ['url_destino' => 'http://example.com/menu'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->put(route('admin.productos.destino', $producto), ['url_destino' => 'https://acortador.example/x'])->assertSessionHasErrors('url_destino');
    }

    public function test_valida_los_datos_y_no_falla_con_entradas_inesperadas(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->put(route('admin.configuracion.update'), $this->datos(['dias_retencion_eventos' => 10]))
            ->assertSessionHasErrors('dias_retencion_eventos');
        $this->actingAs($admin)->put(route('admin.configuracion.update'), $this->datos(['dominios_bloqueados' => 'no es dominio!']))
            ->assertSessionHasErrors();

        // Una lista en lugar de texto no provoca un error 500.
        $this->actingAs($admin)->put(route('admin.configuracion.update'), $this->datos(['dominios_bloqueados' => ['bit.ly', 'tinyurl.com']]))
            ->assertSessionHasNoErrors();
        $this->assertSame(['bit.ly', 'tinyurl.com'], app(AjustesPlataforma::class)->dominiosBloqueados());
    }

    public function test_los_ajustes_se_leen_una_sola_vez_por_peticion(): void
    {
        $cliente = User::factory()->cliente()->create();
        Producto::factory()->count(12)->for($cliente->negocio)->create();

        DB::enableQueryLog();
        $this->actingAs($cliente)->get(route('cliente.productos.index'))->assertOk();
        $consultasCache = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], '`cache`') || str_contains($q['query'], 'configuraciones'));

        // Con CACHE_STORE=array en pruebas no hay consultas a `cache`; lo
        // relevante es que `configuraciones` se lea como máximo una vez.
        $this->assertLessThanOrEqual(1, $consultasCache->filter(fn ($q) => str_contains($q['query'], 'configuraciones'))->count());
    }
}
