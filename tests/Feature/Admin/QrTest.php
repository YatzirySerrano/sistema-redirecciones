<?php

namespace Tests\Feature\Admin;

use App\Models\Negocio;
use App\Models\Producto;
use App\Models\RegistroAuditoria;
use App\Models\User;
use App\Services\Configuracion\AjustesPlataforma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\LeeCodigosQr;
use Tests\TestCase;

class QrTest extends TestCase
{
    use LeeCodigosQr, RefreshDatabase;

    public function test_descarga_png_legible_que_contiene_la_url_permanente(): void
    {
        $admin = User::factory()->create();
        $producto = Producto::factory()->create();

        $respuesta = $this->actingAs($admin)->get(route('productos.qr.show', [$producto, 'formato' => 'png', 'tamano' => 512, 'descargar' => 1]));

        $respuesta->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('attachment;', (string) $respuesta->headers->get('Content-Disposition'));
        $this->assertSame($producto->url_permanente, $this->leerQr($respuesta->getContent()));
    }

    public function test_qr_personalizado_con_color_y_transparencia_sigue_siendo_legible(): void
    {
        $admin = User::factory()->create();
        $producto = Producto::factory()->create();

        $respuesta = $this->actingAs($admin)->get(route('productos.qr.show', [
            $producto, 'formato' => 'png', 'tamano' => 512, 'color' => '#4C1D95', 'fondo' => '#F5F3FF', 'margen' => 'amplio',
        ]));

        $this->assertSame($producto->url_permanente, $this->leerQr($respuesta->getContent()));
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

        $this->assertSame($producto->url_permanente, $this->leerQr($respuesta->getContent()));
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

    /**
     * Negocio con un logotipo PNG válido en el disco privado (fake).
     */
    private function negocioConLogo(): Negocio
    {
        Storage::fake('local');
        $negocio = Negocio::factory()->create();
        $imagen = imagecreatetruecolor(200, 200);
        imagefill($imagen, 0, 0, (int) imagecolorallocate($imagen, 220, 40, 120));
        ob_start();
        imagepng($imagen);
        Storage::disk('local')->put('logos/negocio.png', (string) ob_get_clean());
        $negocio->forceFill(['logo_path' => 'logos/negocio.png'])->save();

        return $negocio;
    }

    public function test_svg_sin_logotipo_contiene_solo_el_codigo(): void
    {
        $admin = User::factory()->create();
        $producto = Producto::factory()->create();

        $svg = (string) $this->actingAs($admin)
            ->get(route('productos.qr.show', [$producto, 'formato' => 'svg', 'tamano' => 512, 'descargar' => 1]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->getContent();

        $this->assertStringContainsString('attachment; filename="qr-', (string) $this->actingAs($admin)
            ->get(route('productos.qr.show', [$producto, 'formato' => 'svg', 'descargar' => 1]))->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString('<image', $svg);
        $this->assertStringNotContainsString('<mask', $svg);
    }

    public function test_svg_con_logotipo_recorta_los_modulos_bajo_el_logo(): void
    {
        $producto = Producto::factory()->for($this->negocioConLogo())->create();
        $admin = User::factory()->create();

        $respuesta = $this->actingAs($admin)->get(route('productos.qr.show', [
            $producto, 'formato' => 'svg', 'tamano' => 1024, 'incluir_logo' => 1, 'descargar' => 1,
        ]));

        $respuesta->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringEndsWith('.svg"', (string) $respuesta->headers->get('Content-Disposition'));

        $svg = simplexml_load_string((string) $respuesta->getContent());
        $this->assertNotFalse($svg);
        $svg->registerXPathNamespace('s', 'http://www.w3.org/2000/svg');

        $imagen = $svg->xpath('//s:image')[0] ?? null;
        $recorte = $svg->xpath('//s:mask[@id="recorte-logo"]/s:rect[@fill="#000000"]')[0] ?? null;
        $modulos = $svg->xpath('//s:path')[0] ?? null;

        $this->assertNotNull($imagen, 'El SVG no incluye el logotipo.');
        $this->assertNotNull($recorte, 'El SVG no recorta los módulos bajo el logotipo.');
        $this->assertSame('url(#recorte-logo)', (string) $modulos['mask']);

        foreach (['x', 'y', 'width', 'height'] as $atributo) {
            $this->assertSame((string) $imagen[$atributo], (string) $recorte[$atributo]);
        }

        // El logotipo ocupa el 20 % del ancho, centrado.
        $this->assertEqualsWithDelta(1024 * 0.2, (float) $imagen['width'], 1);
        $this->assertStringStartsWith('data:image/png;base64,', (string) $imagen['href']);
        $this->assertStringNotContainsStringIgnoringCase('dasti', (string) $respuesta->getContent());
    }

    public function test_la_vista_previa_con_logotipo_funciona_en_svg_y_el_png_equivalente_es_legible(): void
    {
        $producto = Producto::factory()->for($this->negocioConLogo())->create();
        $admin = User::factory()->create();
        $opciones = ['incluir_logo' => 1, 'color' => '#1E3A8A', 'fondo' => '#FFFFFF', 'margen' => 'estandar'];

        // Vista previa tal como la pide el diseñador (fetch, SVG 512 px).
        $this->actingAs($admin)
            ->getJson(route('productos.qr.show', [$producto, 'formato' => 'svg', 'tamano' => 512, ...$opciones]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');

        $png = $this->actingAs($admin)
            ->get(route('productos.qr.show', [$producto, 'formato' => 'png', 'tamano' => 1024, ...$opciones]))
            ->assertOk()
            ->getContent();

        $this->assertSame($producto->url_permanente, $this->leerQr((string) $png));
    }

    public function test_un_logotipo_danado_responde_un_error_controlado_y_no_un_500(): void
    {
        Storage::fake('local');
        $negocio = Negocio::factory()->create();
        Storage::disk('local')->put('logos/danado.png', 'esto no es una imagen');
        $negocio->forceFill(['logo_path' => 'logos/danado.png'])->save();
        $producto = Producto::factory()->for($negocio)->create();
        $admin = User::factory()->create();

        foreach (['svg', 'png'] as $formato) {
            $this->actingAs($admin)
                ->getJson(route('productos.qr.show', [$producto, 'formato' => $formato, 'incluir_logo' => 1]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('qr');
        }

        // No se puede guardar un diseño que use un logotipo inutilizable.
        $this->actingAs($admin)->put(route('productos.qr.update', $producto), ['incluir_logo' => 1])
            ->assertSessionHasErrors('incluir_logo');

        // Sin logotipo el mismo producto sigue generando su QR.
        $this->actingAs($admin)->get(route('productos.qr.show', [$producto, 'formato' => 'svg']))->assertOk();
    }

    public function test_los_errores_de_la_vista_previa_llegan_como_json_con_mensaje(): void
    {
        $admin = User::factory()->create();
        $producto = Producto::factory()->create();

        $this->actingAs($admin)
            ->getJson(route('productos.qr.show', [$producto, 'color' => '#EEEEEE', 'fondo' => '#FFFFFF']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('color');

        $this->actingAs($admin)
            ->getJson(route('productos.qr.show', [$producto, 'tamano' => 999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tamano');
    }

    public function test_el_cliente_descarga_el_qr_de_su_producto_si_la_configuracion_lo_permite(): void
    {
        $producto = Producto::factory()->qr()->create();
        $cliente = User::factory()->cliente($producto->negocio)->create();

        $png = $this->actingAs($cliente)
            ->get(route('productos.qr.show', [$producto, 'formato' => 'png', 'tamano' => 512, 'descargar' => 1]))
            ->assertOk()
            ->getContent();
        $this->assertSame($producto->url_permanente, $this->leerQr((string) $png));

        app(AjustesPlataforma::class)->guardar(['clientes_descargan_qr' => false]);

        $this->actingAs($cliente)->get(route('productos.qr.show', $producto))->assertForbidden();
        $this->actingAs($cliente)->getJson(route('productos.qr.show', $producto))->assertForbidden();
        $this->actingAs($cliente)->put(route('productos.qr.update', $producto), ['color' => '#000000'])->assertForbidden();
        $this->actingAs($cliente)->get(route('cliente.productos.show', $producto))
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('producto.data.permisos.descargar_qr', false));

        // El administrador conserva la descarga.
        $this->actingAs(User::factory()->create())->get(route('productos.qr.show', $producto))->assertOk();
    }

    public function test_un_cliente_no_guarda_el_diseno_de_un_producto_suspendido(): void
    {
        $producto = Producto::factory()->qr()->suspendido()->create();
        $cliente = User::factory()->cliente($producto->negocio)->create();

        $this->actingAs($cliente)->put(route('productos.qr.update', $producto), ['color' => '#1E3A8A'])->assertForbidden();
        $this->assertNull($producto->fresh()->opciones_qr);

        $this->actingAs(User::factory()->create())->put(route('productos.qr.update', $producto), ['color' => '#1E3A8A'])
            ->assertSessionHasNoErrors();
        $this->assertSame('#1E3A8A', $producto->fresh()->opciones_qr['color']);
    }

    public function test_el_producto_ajeno_responde_404_aunque_los_parametros_sean_invalidos(): void
    {
        $ajeno = Producto::factory()->create();
        $cliente = User::factory()->cliente()->create();

        $this->actingAs($cliente)->getJson(route('productos.qr.show', [$ajeno, 'tamano' => 'x', 'color' => 'rojo']))->assertNotFound();
        $this->actingAs($cliente)->put(route('productos.qr.update', $ajeno), ['margen' => 'enorme'])->assertNotFound();
    }

    public function test_guardar_el_diseno_queda_auditado_sin_duplicados(): void
    {
        $admin = User::factory()->create();
        $producto = Producto::factory()->create();

        $this->actingAs($admin)->put(route('productos.qr.update', $producto), ['color' => '#4C1D95'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->put(route('productos.qr.update', $producto), ['color' => '#4C1D95'])->assertSessionHasNoErrors();

        $registros = RegistroAuditoria::query()->where('accion', 'producto.qr_actualizado')->get();
        $this->assertCount(1, $registros);
        $this->assertSame(['color' => '#000000'], $registros[0]->valores_anteriores);
        $this->assertSame(['color' => '#4C1D95'], $registros[0]->valores_nuevos);
    }
}
