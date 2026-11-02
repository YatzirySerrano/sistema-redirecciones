<?php

namespace Tests\Feature\Admin;

use App\Http\Requests\Admin\GuardarNegocioRequest;
use App\Models\Negocio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Coherencia entre la validación en vivo del navegador y Laravel, que
 * conserva la autoridad final.
 */
class ValidacionesEnVivoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function telefonos(): array
    {
        return [
            'con espacios' => ['55 1234 5678', '5512345678'],
            'con guiones y paréntesis' => ['(55) 1234-5678', '5512345678'],
            'con +52' => ['+52 55 1234 5678', '5512345678'],
            'pegado con puntos' => ['55.1234.5678', '5512345678'],
            'incompleto' => ['55 1234', '551234'],
            'vacío' => ['   ', null],
            'con letras se conserva' => ['55-ABCD', '55-ABCD'],
        ];
    }

    #[DataProvider('telefonos')]
    public function test_normaliza_el_telefono_igual_que_el_navegador(string $entrada, ?string $esperado): void
    {
        $this->assertSame($esperado, GuardarNegocioRequest::normalizarTelefono($entrada));
    }

    public function test_guarda_el_telefono_normalizado_y_exige_10_digitos(): void
    {
        $admin = User::factory()->administrador()->create();

        $this->actingAs($admin)->post(route('admin.negocios.store'), [
            'nombre_comercial' => 'Punto Polar Hielo y Agua',
            'telefono' => '+52 (81) 2345-6789',
            'estado' => 'activo',
        ])->assertSessionHasNoErrors();

        $this->assertSame('81 2345 6789', Negocio::query()->sole()->telefono);

        $this->actingAs($admin)->post(route('admin.negocios.store'), [
            'nombre_comercial' => 'Otro',
            'telefono' => '81 2345 67',
            'estado' => 'activo',
        ])->assertSessionHasErrors(['telefono' => 'El teléfono debe tener 10 dígitos (por ejemplo, 55 1234 5678).']);

        $this->actingAs($admin)->post(route('admin.negocios.store'), [
            'nombre_comercial' => 'Otro',
            'telefono' => '81 abc',
            'estado' => 'activo',
        ])->assertSessionHasErrors(['telefono' => 'El teléfono solo puede contener números, espacios y los signos + ( ) - .']);
    }

    public function test_el_endpoint_de_correo_indica_disponibilidad(): void
    {
        $admin = User::factory()->administrador()->create();
        $existente = User::factory()->create(['email' => 'ana@negocio.test']);

        $this->actingAs($admin)->postJson(route('admin.validaciones.correo'), ['email' => ' ANA@negocio.test '])
            ->assertOk()
            ->assertJson(['valido' => true, 'disponible' => false, 'mensaje' => 'Este correo ya está registrado.']);

        $this->actingAs($admin)->postJson(route('admin.validaciones.correo'), ['email' => 'ana@negocio.test', 'ignorar_id' => $existente->id])
            ->assertOk()
            ->assertJson(['disponible' => true]);

        $this->actingAs($admin)->postJson(route('admin.validaciones.correo'), ['email' => 'libre@negocio.test'])
            ->assertJson(['disponible' => true, 'mensaje' => 'El correo está disponible.']);

        $this->actingAs($admin)->postJson(route('admin.validaciones.correo'), ['email' => 'sin-arroba'])
            ->assertJson(['valido' => false, 'mensaje' => 'Ingresa un correo electrónico válido.']);
    }

    public function test_el_endpoint_de_correo_no_esta_disponible_para_clientes_ni_invitados(): void
    {
        $cliente = User::factory()->cliente(Negocio::factory()->create())->create();

        $this->actingAs($cliente)->postJson(route('admin.validaciones.correo'), ['email' => 'x@y.test'])
            ->assertForbidden()
            ->assertJson(['message' => 'No tienes permiso para realizar esta acción.']);

        auth()->logout();

        $this->postJson(route('admin.validaciones.correo'), ['email' => 'x@y.test'])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Tu sesión expiró. Inicia sesión de nuevo.']);
        $this->post(route('admin.validaciones.correo'), ['email' => 'x@y.test'])->assertRedirect(route('login'));
    }
}
