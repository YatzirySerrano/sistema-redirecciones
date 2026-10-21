<?php

namespace Tests\Feature\Admin;

use App\Models\Negocio;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Zxing\QrReader;

class QrTest extends TestCase
{
    use RefreshDatabase;

    private function leer(string $png): ?string
    {
        $texto = (new QrReader($png, QrReader::SOURCE_TYPE_BLOB, false))->text();

        return is_string($texto) ? $texto : null;
    }

    public function test_descarga_png_legible_que_contiene_la_url_permanente(): void
    {
        $admin = User::factory()->create();
        $producto = Producto::factory()->create();

        $respuesta = $this->actingAs($admin)->get(route('productos.qr.show', [$producto, 'formato' => 'png', 'tamano' => 512, 'descargar' => 1]));

        $respuesta->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('attachment;', (string) $respuesta->headers->get('Content-Disposition'));
        $this->assertSame($producto->url_permanente, $this->leer($respuesta->getContent()));
    }

    public function test_qr_personalizado_con_color_y_transparencia_sigue_siendo_legible(): void
    {
        $admin = User::factory()->create();
        $producto = Producto::factory()->create();

        $respuesta = $this->actingAs($admin)->get(route('productos.qr.show', [
            $producto, 'formato' => 'png', 'tamano' => 512, 'color' => '#4C1D95', 'fondo' => '#F5F3FF', 'margen' => 'amplio',
        ]));

        $this->assertSame($producto->url_permanente, $this->leer($respuesta->getContent()));
    }

    public function test_qr_con_logotipo_del_negocio_sigue_siendo_legible(): void
    {
        Storage::fake('local');
        $negocio = Negocio::factory()->create();
        $imagen = imagecreatetruecolor(200, 200);
        imagefill($imagen, 0, 0, (int) imagecolorallocate($imagen, 220, 40, 120));
        ob_start();
        imagepng($imagen);
        Storage::disk('local')->put('logos/prueba.png', (string) ob_get_clean());
        $negocio->forceFill(['logo_path' => 'logos/prueba.png'])->save();

        $admin = User::factory()->create();
        $producto = Producto::factory()->for($negocio)->create();

        $respuesta = $this->actingAs($admin)->get(route('productos.qr.show', [$producto, 'formato' => 'png', 'tamano' => 1024, 'incluir_logo' => 1]));

        $this->assertSame($producto->url_permanente, $this->leer($respuesta->getContent()));
    }

    public function test_svg_sin_marca_de_dasti(): void
    {
        $admin = User::factory()->create();
        $producto = Producto::factory()->create();

        $respuesta = $this->actingAs($admin)->get(route('productos.qr.show', [$producto, 'formato' => 'svg']));

        $respuesta->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $svg = (string) $respuesta->getContent();
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringNotContainsStringIgnoringCase('dasti', $svg);
        $this->assertStringNotContainsString('<script', $svg);
    }

    public function test_rechaza_disenos_ilegibles(): void
    {
        $admin = User::factory()->create();
        $producto = Producto::factory()->create();

        $this->actingAs($admin)->get(route('productos.qr.show', [$producto, 'color' => '#EEEEEE', 'fondo' => '#FFFFFF']))->assertSessionHasErrors('color');
        $this->actingAs($admin)->put(route('productos.qr.update', $producto), ['color' => '#FFFFFF', 'fondo' => '#000000'])->assertSessionHasErrors('color');
        $this->actingAs($admin)->put(route('productos.qr.update', $producto), ['incluir_logo' => 1])->assertSessionHasErrors('incluir_logo');
    }

    public function test_guarda_el_diseno_vinculado_al_producto(): void
    {
        $admin = User::factory()->create();
        $producto = Producto::factory()->create();

        $this->actingAs($admin)->put(route('productos.qr.update', $producto), ['color' => '#1E3A8A', 'fondo_transparente' => 1, 'margen' => 'reducido'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['color' => '#1E3A8A', 'fondo' => '#FFFFFF', 'fondo_transparente' => true, 'margen' => 'reducido', 'incluir_logo' => false], $producto->fresh()->opciones_qr);
    }
}
