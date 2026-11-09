<?php

namespace Tests\Feature\Qr;

use App\Models\Negocio;
use App\Models\Producto;
use App\Models\User;
use App\Services\Qr\GeneradorQr;
use App\Services\Qr\InsigniaOjosQr;
use App\Services\Qr\OpcionesQr;
use Endroid\QrCode\Bacon\MatrixFactory;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Matrix\MatrixInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use GdImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use SimpleXMLElement;
use Tests\Concerns\LeeCodigosQr;
use Tests\TestCase;
use Zxing\QrReader;

/**
 * Los tres ojos del QR se dibujan limpios (sin logotipo de la plataforma),
 * en el color del código y con el estilo elegido, y siguen siendo legibles.
 */
class QrMarcaTest extends TestCase
{
    use LeeCodigosQr, RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string, 2: bool, 3: string}>
     */
    public static function disenos(): array
    {
        return [
            'por defecto' => ['#000000', '#FFFFFF', false, 'estandar'],
            'violeta sobre lila' => ['#4C1D95', '#F5F3FF', false, 'amplio'],
            'azul transparente' => ['#1E3A8A', '#FFFFFF', true, 'reducido'],
            'verde sin margen' => ['#14532D', '#FEF9C3', false, 'ninguno'],
            'guinda' => ['#7F1D1D', '#FFFFFF', false, 'estandar'],
        ];
    }

    #[DataProvider('disenos')]
    public function test_el_png_con_insignia_se_detecta_y_decodifica_en_todos_los_tamanos(string $color, string $fondo, bool $transparente, string $margen): void
    {
        $producto = Producto::factory()->create();
        $opciones = new OpcionesQr($color, $fondo, $transparente, $margen);

        foreach (OpcionesQr::TAMANOS as $tamano) {
            $png = app(GeneradorQr::class)->generar($producto, $opciones, 'png', $tamano)['contenido'];

            $this->assertSame($producto->url_permanente, $this->detectarComoCamara($png), "Tamaño {$tamano}: el detector no localiza los ojos.");

            if ($tamano <= 1024) {
                $this->assertSame($producto->url_permanente, $this->leerQr($png), "Tamaño {$tamano}: no se decodifica a tamaño completo.");
            }
        }
    }

    public function test_el_png_con_insignia_y_logotipo_del_negocio_sigue_siendo_legible(): void
    {
        $producto = Producto::factory()->for($this->negocioConLogo())->create();
        $generador = app(GeneradorQr::class);

        foreach ([[512, false], [1024, false], [1024, true], [2048, false]] as [$tamano, $transparente]) {
            $opciones = new OpcionesQr('#1E3A8A', '#FFFFFF', $transparente, 'estandar', incluirLogo: true);
            $png = $generador->generar($producto, $opciones, 'png', $tamano)['contenido'];

            $this->assertSame($producto->url_permanente, $this->detectarComoCamara($png), "Tamaño {$tamano} con logotipo.");
        }
    }

    public function test_los_ojos_del_png_son_limpios_del_color_del_codigo_sin_logotipo(): void
    {
        $producto = Producto::factory()->create();
        $opciones = new OpcionesQr('#7F1D1D', '#FFFFFF', false, 'amplio');
        $imagen = imagecreatefromstring(app(GeneradorQr::class)->generar($producto, $opciones, 'png', 1024)['contenido']);
        $this->assertInstanceOf(GdImage::class, $imagen);

        $matriz = $this->matriz($producto, 1024, 'amplio');
        $this->assertSame($matriz->getOuterSize(), imagesx($imagen));

        $b = $matriz->getBlockSize();
        $m = $matriz->getMarginLeft();
        $pixel = function (float $columna, float $fila) use ($imagen, $b, $m): array {
            $c = imagecolorat($imagen, (int) ($m + $columna * $b), (int) ($m + $fila * $b));

            return [($c >> 16) & 255, ($c >> 8) & 255, $c & 255];
        };

        foreach (InsigniaOjosQr::origenes($matriz) as [$c, $f]) {
            $this->assertSame([0x7F, 0x1D, 0x1D], $pixel($c + 0.5, $f + 3.5), 'Anillo oscuro del color del código.');
            $this->assertSame([255, 255, 255], $pixel($c + 1.5, $f + 3.5), 'Anillo claro del color de fondo.');

            // El cuadro central de 3×3 es de un solo color (el del código):
            // ningún favicon, placa ni otro tono dentro del ojo.
            for ($i = 0; $i < 40; $i++) {
                for ($j = 0; $j < 40; $j++) {
                    $this->assertSame([0x7F, 0x1D, 0x1D], $pixel($c + 2.3 + $i * 0.06, $f + 2.3 + $j * 0.06), 'Centro del ojo sin logotipo.');
                }
            }
        }
    }

    public function test_el_svg_dibuja_los_tres_ojos_limpios_sin_marca_de_la_plataforma(): void
    {
        $producto = Producto::factory()->create();
        $opciones = new OpcionesQr('#4C1D95', estiloOjos: 'cuadrado', estiloModulos: 'cuadrado');
        $svg = app(GeneradorQr::class)->generar($producto, $opciones, 'svg', 512)['contenido'];

        $this->assertStringNotContainsStringIgnoringCase('dasti', $svg);
        $this->assertStringNotContainsString('<text', $svg);
        $this->assertStringNotContainsString('<script', $svg);
        $this->assertStringNotContainsString('<image', $svg, 'Sin imágenes: ni favicon en los ojos ni logotipo.');

        $xml = $this->xml($svg);
        $insignias = $this->buscar($xml, '//s:g[@class="'.InsigniaOjosQr::CLASE_SVG.'"]');
        $this->assertCount(3, $insignias);

        foreach ($insignias as $insignia) {
            $trazos = $this->buscar($insignia, 's:path');

            $this->assertCount(0, $this->buscar($insignia, 's:image'));
            $this->assertCount(2, $trazos, 'Marco y cuadro central.');
            $this->assertSame('#4c1d95', (string) $trazos[0]['fill']);
            $this->assertSame('#4c1d95', (string) $trazos[1]['fill'], 'Cuadro central del color del código.');
        }

        // El trazado de módulos ya no dibuja los ojos (los dibuja la insignia).
        $matriz = $this->matriz($producto, 512, 'estandar');
        $b = $matriz->getBlockSize();
        $m = $matriz->getMarginLeft();
        preg_match_all('/M([\d.]+),([\d.]+)/', (string) $this->buscar($xml, '/s:svg/s:path')[0]['d'], $inicios, PREG_SET_ORDER);
        $this->assertNotEmpty($inicios);

        foreach ($inicios as [, $x, $y]) {
            $columna = (int) round(((float) $x - $m) / $b);
            $fila = (int) round(((float) $y - $m) / $b);
            $this->assertFalse(InsigniaOjosQr::enOjo($matriz, $fila, $columna), "Módulo ({$fila}, {$columna}) dentro de un ojo.");
        }

        // Cada marco empieza exactamente en la esquina del ojo según la matriz.
        foreach (InsigniaOjosQr::origenes($matriz) as $i => [$c, $f]) {
            $esperado = 'M'.($m + $c * $b).','.($m + $f * $b).'H';
            $this->assertStringStartsWith($esperado, (string) $this->buscar($insignias[$i], 's:path')[0]['d']);
        }
    }

    public function test_el_svg_con_logotipo_conserva_el_recorte_y_los_ojos_limpios(): void
    {
        $producto = Producto::factory()->for($this->negocioConLogo())->create();
        $svg = app(GeneradorQr::class)->generar($producto, new OpcionesQr(incluirLogo: true), 'svg', 1024)['contenido'];
        $xml = $this->xml($svg);

        $this->assertCount(1, $this->buscar($xml, '/s:svg/s:image'), 'El logotipo del negocio sigue al centro.');
        $this->assertCount(3, $this->buscar($xml, '//s:g[@class="'.InsigniaOjosQr::CLASE_SVG.'"]'));
        $this->assertCount(1, $this->buscar($xml, '//s:image'), 'La única imagen es el logotipo del negocio.');
        $this->assertSame('url(#recorte-logo)', (string) $this->buscar($xml, '/s:svg/s:path')[0]['mask']);
        $this->assertStringNotContainsStringIgnoringCase('dasti', $svg);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function estilos(): array
    {
        $casos = [];

        foreach (OpcionesQr::ESTILOS_OJOS as $ojos) {
            foreach (OpcionesQr::ESTILOS_MODULOS as $modulos) {
                $casos["ojos {$ojos}, módulos {$modulos}"] = [$ojos, $modulos];
            }
        }

        return $casos;
    }

    #[DataProvider('estilos')]
    public function test_cada_estilo_de_ojos_y_modulos_se_detecta_y_decodifica(string $ojos, string $modulos): void
    {
        $producto = Producto::factory()->create();
        $generador = app(GeneradorQr::class);

        foreach ([['#000000', '#FFFFFF', false, 'estandar', 512], ['#4C1D95', '#F5F3FF', false, 'amplio', 1024], ['#1E3A8A', '#FFFFFF', true, 'reducido', 2048]] as [$color, $fondo, $transparente, $margen, $tamano]) {
            $opciones = new OpcionesQr($color, $fondo, $transparente, $margen, estiloOjos: $ojos, estiloModulos: $modulos);
            $png = $generador->generar($producto, $opciones, 'png', $tamano)['contenido'];

            $this->assertSame($producto->url_permanente, $this->detectarComoCamara($png), "{$ojos}/{$modulos} {$tamano}: el detector no localiza el código.");
            $this->assertOjosLimpios($producto, $png, $color, $tamano, $margen, "{$ojos}/{$modulos} {$tamano}");
        }
    }

    public function test_por_defecto_los_ojos_y_los_modulos_son_redondeados(): void
    {
        $opciones = OpcionesQr::desdeArreglo(null);

        $this->assertSame('redondeado', $opciones->estiloOjos);
        $this->assertSame('redondeado', $opciones->estiloModulos);
        $this->assertSame('redondeado', OpcionesQr::desdeArreglo(['estilo_ojos' => 'otro', 'estilo_modulos' => '<x>'])->estiloModulos);

        $producto = Producto::factory()->create();
        $svg = app(GeneradorQr::class)->generar($producto, $opciones, 'svg', 512)['contenido'];
        $xml = $this->xml($svg);

        // Marcos y módulos con arcos (esquinas redondeadas).
        foreach ($this->buscar($xml, '//s:g[@class="'.InsigniaOjosQr::CLASE_SVG.'"]/s:path[1]') as $marco) {
            $this->assertStringContainsString('A', (string) $marco['d']);
        }
        $this->assertStringContainsString('A', (string) $this->buscar($xml, '/s:svg/s:path')[0]['d']);
    }

    public function test_el_svg_de_puntos_dibuja_circulos_y_ojos_sin_logotipo(): void
    {
        $producto = Producto::factory()->create();
        $svg = app(GeneradorQr::class)->generar($producto, new OpcionesQr(estiloOjos: 'hoja', estiloModulos: 'puntos'), 'svg', 512)['contenido'];
        $xml = $this->xml($svg);

        $this->assertMatchesRegularExpression('/a[\d.]+,[\d.]+ 0 1 0 /', (string) $this->buscar($xml, '/s:svg/s:path')[0]['d']);
        $this->assertCount(3, $this->buscar($xml, '//s:g[@class="'.InsigniaOjosQr::CLASE_SVG.'"]'));
        $this->assertCount(0, $this->buscar($xml, '//s:image'));
    }

    public function test_los_estilos_se_validan_y_se_guardan_en_el_diseno(): void
    {
        $admin = User::factory()->create();
        $producto = Producto::factory()->create();

        $this->actingAs($admin)->put(route('productos.qr.update', $producto), ['estilo_ojos' => 'estrella'])->assertSessionHasErrors('estilo_ojos');
        $this->actingAs($admin)->get(route('productos.qr.show', [$producto, 'estilo_modulos' => 'triangulos']))->assertSessionHasErrors('estilo_modulos');

        $this->actingAs($admin)->put(route('productos.qr.update', $producto), ['estilo_ojos' => 'hoja', 'estilo_modulos' => 'puntos'])->assertSessionHasNoErrors();

        $this->assertSame('hoja', $producto->fresh()->opciones_qr['estilo_ojos']);
        $this->assertSame('puntos', $producto->fresh()->opciones_qr['estilo_modulos']);
    }

    public function test_la_respuesta_svg_incluye_los_tres_ojos_y_respeta_la_politica_de_contenido(): void
    {
        $producto = Producto::factory()->create();

        $respuesta = $this->actingAs(User::factory()->create())
            ->get(route('productos.qr.show', [$producto, 'formato' => 'svg']));

        $respuesta->assertOk();
        $this->assertStringContainsString('img-src data:', (string) $respuesta->headers->get('Content-Security-Policy'));
        $this->assertSame(3, substr_count((string) $respuesta->getContent(), 'class="'.InsigniaOjosQr::CLASE_SVG.'"'));
    }

    /**
     * Lectura con el detector real (sin muestreo directo de la rejilla), sobre
     * la imagen reducida al tamaño con que la vería la cámara de un teléfono.
     * Se prueban dos escalas porque el puerto PHP de ZXing falla en ~3 % de
     * los códigos válidos aun sin insignia.
     */
    private function detectarComoCamara(string $png): ?string
    {
        $original = imagecreatefromstring($png);
        $this->assertInstanceOf(GdImage::class, $original);

        $texto = null;

        foreach ([360, 440, 300] as $lado) {
            $lienzo = imagecreatetruecolor($lado, $lado);
            imagefill($lienzo, 0, 0, (int) imagecolorallocate($lienzo, 255, 255, 255));
            imagecopyresampled($lienzo, $original, 0, 0, 0, 0, $lado, $lado, imagesx($original), imagesy($original));
            ob_start();
            imagepng($lienzo);
            $texto = (new QrReader((string) ob_get_clean(), QrReader::SOURCE_TYPE_BLOB, false))->text();

            if (is_string($texto) && $texto !== '') {
                return $texto;
            }
        }

        return null;
    }

    /**
     * El cuadro central (3×3) de cada ojo es completamente del color del
     * código: no queda ningún resto del favicon ni de la placa.
     */
    private function assertOjosLimpios(Producto $producto, string $png, string $color, int $tamano, string $margen, string $caso): void
    {
        $imagen = imagecreatefromstring($png);
        $this->assertInstanceOf(GdImage::class, $imagen);
        $esperado = OpcionesQr::rgb($color);
        $matriz = $this->matriz($producto, $tamano, $margen);
        $b = $matriz->getBlockSize();
        $m = $matriz->getMarginLeft();

        foreach (InsigniaOjosQr::origenes($matriz) as [$c, $f]) {
            for ($i = 0; $i <= 10; $i++) {
                for ($j = 0; $j <= 10; $j++) {
                    $p = imagecolorat($imagen, (int) ($m + ($c + 2.8 + $i * 0.14) * $b), (int) ($m + ($f + 2.8 + $j * 0.14) * $b));
                    $this->assertSame($esperado, [($p >> 16) & 255, ($p >> 8) & 255, $p & 255], "{$caso}: centro del ojo con otro color.");
                }
            }
        }
    }

    /**
     * Misma matriz que construye endroid con los parámetros de GeneradorQr.
     */
    private function matriz(Producto $producto, int $tamano, string $margen): MatrixInterface
    {
        return (new MatrixFactory)->create(new QrCode(
            data: $producto->url_permanente,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Quartile,
            size: $tamano,
            margin: (int) round($tamano * OpcionesQr::MARGENES[$margen]),
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ));
    }

    /**
     * @return list<SimpleXMLElement>
     */
    private function buscar(SimpleXMLElement $nodo, string $consulta): array
    {
        $nodo->registerXPathNamespace('s', 'http://www.w3.org/2000/svg');
        $resultado = $nodo->xpath($consulta);
        $this->assertIsArray($resultado, "Consulta XPath inválida: {$consulta}");

        return array_values($resultado);
    }

    private function xml(string $svg): SimpleXMLElement
    {
        $xml = simplexml_load_string($svg);
        $this->assertInstanceOf(SimpleXMLElement::class, $xml);
        $xml->registerXPathNamespace('s', 'http://www.w3.org/2000/svg');

        return $xml;
    }

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
}
