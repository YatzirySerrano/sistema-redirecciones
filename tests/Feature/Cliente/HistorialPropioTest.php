<?php

namespace Tests\Feature\Cliente;

use App\Enums\OrigenCambio;
use App\Models\CambioDestino;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Historial de cambios del cliente: cada usuario ve solo los cambios hechos
 * con su propia cuenta. Los de DASTI, de otros usuarios o automáticos se
 * siguen registrando y los ve el personal autorizado.
 */
class HistorialPropioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $superadmin;

    private Negocio $negocio;

    private User $cliente;

    private User $companero;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->admin = User::factory()->create();
        $this->superadmin = User::factory()->superadministrador()->create();
        $this->negocio = Negocio::factory()->create();
        $this->cliente = User::factory()->cliente($this->negocio)->create();
        $this->companero = User::factory()->cliente($this->negocio)->create();
        $this->producto = Producto::factory()->for($this->negocio)->create(['tipo' => 'qr', 'url_destino' => 'https://www.example.org/inicial']);
    }

    private function destinoCliente(User $usuario, string $url): void
    {
        $this->actingAs($usuario)->put(route('cliente.productos.destino', $this->producto), ['url_destino' => $url])->assertSessionHasNoErrors();
    }

    private function destinoAdmin(User $usuario, string $url): void
    {
        $this->actingAs($usuario)->put(route('admin.productos.destino', $this->producto), ['url_destino' => $url, 'motivo' => 'Ajuste'])->assertSessionHasNoErrors();
    }

    /**
     * @return list<string|null>
     */
    private function historialDe(User $usuario, string $ruta = 'ficha'): array
    {
        $respuesta = $ruta === 'ficha'
            ? $this->actingAs($usuario)->get(route('cliente.productos.show', $this->producto))
            : $this->actingAs($usuario)->get(route('cliente.inicio'));
        $prop = $ruta === 'ficha' ? 'cambios' : 'actividad';
        $datos = $respuesta->assertOk()->viewData('page')['props'][$prop]['data'];

        return array_column($datos, 'url_nueva');
    }

    public function test_cada_usuario_ve_solo_sus_propios_cambios(): void
    {
        $this->destinoAdmin($this->admin, 'https://www.example.org/admin');
        $this->destinoAdmin($this->superadmin, 'https://www.example.org/super');
        $this->destinoCliente($this->cliente, 'https://www.example.org/cliente');
        $this->destinoCliente($this->companero, 'https://www.example.org/companero');
        CambioDestino::query()->create([
            'producto_id' => $this->producto->id, 'negocio_id' => $this->negocio->id, 'user_id' => null,
            'origen' => OrigenCambio::Sistema, 'url_anterior' => null, 'url_nueva' => 'https://www.example.org/sistema',
        ]);

        foreach (['ficha', 'inicio'] as $vista) {
            $this->assertSame(['https://www.example.org/cliente'], $this->historialDe($this->cliente, $vista), "Cliente en {$vista}.");
            $this->assertSame(['https://www.example.org/companero'], $this->historialDe($this->companero, $vista), "Compañero en {$vista}.");
        }

        // El HTML tampoco lleva los cambios ajenos (no se ocultan en el navegador).
        $html = (string) $this->actingAs($this->cliente)->get(route('cliente.productos.show', $this->producto))->getContent();
        foreach (['admin', 'super', 'companero', 'sistema'] as $ajeno) {
            $this->assertStringNotContainsString("example.org/{$ajeno}", $html);
        }
    }

    public function test_sin_cambios_propios_el_historial_esta_vacio_y_el_estado_actual_se_ve(): void
    {
        $this->destinoAdmin($this->admin, 'https://www.example.org/de-dasti');

        $this->actingAs($this->cliente)->get(route('cliente.productos.show', $this->producto))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->has('cambios.data', 0)
                ->where('producto.data.url_destino', 'https://www.example.org/de-dasti'));
        $this->assertSame([], $this->historialDe($this->cliente, 'inicio'));

        // La redirección usa el destino vigente.
        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Safari/604.1')
            ->get('/r/'.$this->producto->codigo)->assertRedirect('https://www.example.org/de-dasti');
    }

    public function test_el_personal_de_dasti_conserva_todo_el_historial_y_nada_se_borra(): void
    {
        $this->destinoAdmin($this->admin, 'https://www.example.org/admin');
        $this->destinoCliente($this->cliente, 'https://www.example.org/cliente');
        $this->destinoAdmin($this->superadmin, 'https://www.example.org/super');
        $registros = CambioDestino::query()->orderBy('id')->get(['id', 'user_id', 'origen', 'url_anterior', 'url_nueva'])->toArray();

        foreach ([$this->admin, $this->superadmin] as $personal) {
            $this->actingAs($personal)->get(route('admin.productos.show', $this->producto))
                ->assertInertia(fn (Assert $p) => $p->has('cambios.data', 3));
            $this->actingAs($personal)->get(route('admin.negocios.show', $this->negocio))
                ->assertInertia(fn (Assert $p) => $p->has('cambios.data', 3));
        }

        // Consultar el historial del cliente no altera los registros ni su autoría.
        $this->historialDe($this->cliente);
        $this->assertSame($registros, CambioDestino::query()->orderBy('id')->get(['id', 'user_id', 'origen', 'url_anterior', 'url_nueva'])->toArray());
        $this->assertSame(
            [$this->admin->id, $this->cliente->id, $this->superadmin->id],
            array_column($registros, 'user_id'),
        );
    }

    public function test_el_limite_de_la_lista_se_aplica_solo_sobre_los_cambios_propios(): void
    {
        // Muchos cambios de DASTI recientes no desplazan los del cliente.
        $this->destinoCliente($this->cliente, 'https://www.example.org/propio-1');
        $this->destinoCliente($this->cliente, 'https://www.example.org/propio-2');
        for ($i = 0; $i < 15; $i++) {
            $this->destinoAdmin($this->admin, "https://www.example.org/admin-{$i}");
        }

        $this->assertSame(['https://www.example.org/propio-2', 'https://www.example.org/propio-1'], $this->historialDe($this->cliente));
        $this->assertSame(['https://www.example.org/propio-2', 'https://www.example.org/propio-1'], $this->historialDe($this->cliente, 'inicio'));
    }

    public function test_no_se_pueden_consultar_historiales_ajenos_manipulando_identificadores(): void
    {
        $otroNegocio = Negocio::factory()->create();
        $ajeno = Producto::factory()->for($otroNegocio)->create(['tipo' => 'qr']);

        $this->actingAs($this->cliente)->get(route('cliente.productos.show', $ajeno))->assertNotFound();
        // Parámetros extra no amplían el alcance.
        $this->destinoAdmin($this->admin, 'https://www.example.org/admin');
        $this->actingAs($this->cliente)->get(route('cliente.productos.show', [$this->producto, 'user_id' => $this->admin->id, 'todos' => 1]))
            ->assertInertia(fn (Assert $p) => $p->has('cambios.data', 0));
    }
}
