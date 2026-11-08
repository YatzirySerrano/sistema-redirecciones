<?php

namespace App\Services\Qr;

use Endroid\QrCode\Matrix\MatrixInterface;
use GdImage;
use RuntimeException;
use SimpleXMLElement;

/**
 * Dibuja la insignia de la plataforma (su favicon) dentro de los tres
 * patrones de localización («ojos») de un QR ya generado por endroid/qr-code.
 *
 * Cada ojo conserva su marco de 7×7 módulos en el color del código (anillo
 * oscuro y anillo claro, de esquinas rectas: los detectores clásicos, como
 * el de OpenCV, buscan cuadriláteros y fallan con esquinas redondeadas). El
 * cuadro central de 3×3 se sustituye por una placa azul marino con el favicon
 * encima.
 *
 * Por qué el favicon va en tonos de azul puro y no con sus colores
 * originales: los lectores tipo ZXing binarizan con umbrales locales, y
 * cuando el código se lee de cerca el umbral se calcula con píxeles que caen
 * por completo dentro de la placa. Cualquier detalle claro del favicon (el
 * degradado celeste, el «</>») queda entonces como «claro», rompe la
 * proporción 1:1:3:1:1 y el ojo deja de detectarse (se comprobó con ZXing y
 * jsQR: con el favicon original menos de la mitad de las lecturas cercanas
 * funcionaban). El azul puro aporta muy poca luminancia, de modo que placa y
 * favicon difieren menos que el rango mínimo que esos lectores consideran
 * contraste y el centro se binariza entero como oscuro, mientras el ojo
 * humano sí distingue el favicon. El recurso `resources/marca/insignia-qr.png`
 * es el favicon de DASTI ya recoloreado así (canal azul entre 150 y 215,
 * rojo y verde en 0).
 *
 * Las posiciones se calculan desde la matriz real (tamaño de bloque y
 * margen), nunca a partir de píxeles supuestos.
 */
class InsigniaOjosQr
{
    /** Lado del patrón de localización, en módulos. */
    public const LADO_OJO = 7;

    /** Clase que identifica los grupos de la insignia dentro del SVG. */
    public const CLASE_SVG = 'insignia-ojo';

    /** Color de la placa central (azul marino, luminancia similar al favicon). */
    public const COLOR_PLACA = [0, 0, 120];

    // Medidas en módulos. La placa ocupa el cuadro central de 3×3; el
    // favicon, un poco menos para que la placa lo enmarque.
    private const LADO_PLACA = 3.0;

    private const RADIO_PLACA = 0.45;

    private const ALTO_FAVICON = 2.6;

    private const RUTA_FAVICON = 'marca/insignia-qr.png';

    private ?string $bytesFavicon = null;

    private ?GdImage $favicon = null;

    /**
     * Esquinas superiores izquierdas (columna, fila) de los tres ojos.
     *
     * @return list<array{0: int, 1: int}>
     */
    public static function origenes(MatrixInterface $matriz): array
    {
        $n = $matriz->getBlockCount();

        return [[0, 0], [$n - self::LADO_OJO, 0], [0, $n - self::LADO_OJO]];
    }

    public static function enOjo(MatrixInterface $matriz, int $fila, int $columna): bool
    {
        foreach (self::origenes($matriz) as [$c, $f]) {
            if ($columna >= $c && $columna < $c + self::LADO_OJO && $fila >= $f && $fila < $f + self::LADO_OJO) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}  $color
     * @param  array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}  $fondo
     */
    public function aplicarPng(GdImage $imagen, MatrixInterface $matriz, array $color, array $fondo, bool $transparente): void
    {
        $bloque = $matriz->getBlockSize();
        $margen = $matriz->getMarginLeft();
        $lado = (int) round(self::LADO_OJO * $bloque);

        // Se dibuja con sobremuestreo y se reduce: GD no suaviza los bordes de
        // las figuras rellenas ni del favicon escalado.
        $escala = max(2, min(12, (int) ceil(700 / max(1, $lado))));
        $ojo = $this->dibujarOjo($lado * $escala, $color, $fondo, $transparente);

        imagealphablending($imagen, false);
        imagesavealpha($imagen, true);

        foreach (self::origenes($matriz) as [$columna, $fila]) {
            imagecopyresampled(
                $imagen, $ojo,
                (int) round($margen + $columna * $bloque), (int) round($margen + $fila * $bloque),
                0, 0, $lado, $lado, $lado * $escala, $lado * $escala,
            );
        }

        imagealphablending($imagen, true);
    }

    /**
     * Sustituye los módulos de los ojos del trazado de endroid por el marco,
     * la placa y el favicon.
     *
     * @param  array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}  $color
     */
    public function aplicarSvg(SimpleXMLElement $svg, MatrixInterface $matriz, array $color): void
    {
        $modulos = $svg->path[0] ?? null;

        if (! $modulos instanceof SimpleXMLElement) {
            return;
        }

        $modulos['d'] = $this->trazadoSinOjos($matriz);

        $hex = sprintf('#%02x%02x%02x', ...$color);
        $hexPlaca = sprintf('#%02x%02x%02x', ...self::COLOR_PLACA);
        $b = $matriz->getBlockSize();
        $m = $matriz->getMarginLeft();
        $uri = 'data:image/png;base64,'.base64_encode($this->bytesFavicon());
        [$anchoFav, $altoFav] = $this->medidasFavicon(self::ALTO_FAVICON * $b);
        $inicioPlaca = (self::LADO_OJO - self::LADO_PLACA) / 2 * $b;

        foreach (self::origenes($matriz) as [$columna, $fila]) {
            $x = $m + $columna * $b;
            $y = $m + $fila * $b;

            $grupo = $svg->addChild('g');
            $grupo->addAttribute('class', self::CLASE_SVG);

            // Anillo oscuro: cuadrado de 7 módulos menos el hueco de 5.
            $marco = $grupo->addChild('path');
            $marco->addAttribute('fill', $hex);
            $marco->addAttribute('fill-rule', 'evenodd');
            $marco->addAttribute('d', $this->cuadradoSvg($x, $y, 7 * $b).$this->cuadradoSvg($x + $b, $y + $b, 5 * $b));

            $placa = $grupo->addChild('rect');
            $placa->addAttribute('x', $this->numero($x + $inicioPlaca));
            $placa->addAttribute('y', $this->numero($y + $inicioPlaca));
            $placa->addAttribute('width', $this->numero(self::LADO_PLACA * $b));
            $placa->addAttribute('height', $this->numero(self::LADO_PLACA * $b));
            $placa->addAttribute('rx', $this->numero(self::RADIO_PLACA * $b));
            $placa->addAttribute('fill', $hexPlaca);

            $imagen = $grupo->addChild('image');
            $imagen->addAttribute('x', $this->numero($x + 3.5 * $b - $anchoFav / 2));
            $imagen->addAttribute('y', $this->numero($y + 3.5 * $b - $altoFav / 2));
            $imagen->addAttribute('width', $this->numero($anchoFav));
            $imagen->addAttribute('height', $this->numero($altoFav));
            $imagen->addAttribute('href', $uri);
        }
    }

    /**
     * @param  array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}  $color
     * @param  array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}  $fondo
     */
    private function dibujarOjo(int $lado, array $color, array $fondo, bool $transparente): GdImage
    {
        $u = $lado / self::LADO_OJO;
        $ojo = $this->lienzo($lado, $lado);

        imagealphablending($ojo, false);
        $claro = (int) imagecolorallocatealpha($ojo, $fondo[0], $fondo[1], $fondo[2], $transparente ? 127 : 0);
        $oscuro = (int) imagecolorallocatealpha($ojo, $color[0], $color[1], $color[2], 0);
        $placa = (int) imagecolorallocatealpha($ojo, self::COLOR_PLACA[0], self::COLOR_PLACA[1], self::COLOR_PLACA[2], 0);

        imagefilledrectangle($ojo, 0, 0, $lado - 1, $lado - 1, $oscuro);
        imagefilledrectangle($ojo, (int) round($u), (int) round($u), (int) round(6 * $u) - 1, (int) round(6 * $u) - 1, $claro);

        $inicioPlaca = (self::LADO_OJO - self::LADO_PLACA) / 2 * $u;
        $this->rectanguloRedondeado($ojo, $inicioPlaca, $inicioPlaca, self::LADO_PLACA * $u, self::RADIO_PLACA * $u, $placa);

        // El favicon se mezcla sobre la placa conservando su transparencia.
        $favicon = $this->favicon();
        [$ancho, $alto] = $this->medidasFavicon(self::ALTO_FAVICON * $u);
        imagealphablending($ojo, true);
        imagecopyresampled(
            $ojo, $favicon,
            (int) round(3.5 * $u - $ancho / 2), (int) round(3.5 * $u - $alto / 2),
            0, 0, (int) round($ancho), (int) round($alto), imagesx($favicon), imagesy($favicon),
        );
        imagealphablending($ojo, false);

        return $ojo;
    }

    private function rectanguloRedondeado(GdImage $imagen, float $x, float $y, float $lado, float $radio, int $color): void
    {
        $x1 = (int) round($x);
        $y1 = (int) round($y);
        $x2 = (int) round($x + $lado) - 1;
        $y2 = (int) round($y + $lado) - 1;
        $r = (int) round($radio);
        $d = 2 * $r;

        imagefilledrectangle($imagen, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($imagen, $x1, $y1 + $r, $x2, $y2 - $r, $color);

        foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
            imagefilledellipse($imagen, $cx, $cy, $d, $d, $color);
        }
    }

    private function cuadradoSvg(float $x, float $y, float $lado): string
    {
        return 'M'.$this->numero($x).','.$this->numero($y)
            .'H'.$this->numero($x + $lado).'V'.$this->numero($y + $lado)
            .'H'.$this->numero($x).'Z';
    }

    /**
     * Mismo trazado compacto que genera endroid, sin los módulos de los ojos.
     */
    private function trazadoSinOjos(MatrixInterface $matriz): string
    {
        $n = $matriz->getBlockCount();
        $b = $matriz->getBlockSize();
        $m = $matriz->getMarginLeft();
        $trazado = '';

        for ($fila = 0; $fila < $n; $fila++) {
            $inicio = null;

            for ($columna = 0; $columna <= $n; $columna++) {
                $oscuro = $columna < $n
                    && $matriz->getBlockValue($fila, $columna) === 1
                    && ! self::enOjo($matriz, $fila, $columna);

                if ($oscuro && $inicio === null) {
                    $inicio = $columna;
                } elseif (! $oscuro && $inicio !== null) {
                    $trazado .= 'M'.$this->numero($m + $b * $inicio).','.$this->numero($m + $b * $fila)
                        .'H'.$this->numero($m + $b * $columna)
                        .'V'.$this->numero($m + $b * ($fila + 1))
                        .'H'.$this->numero($m + $b * $inicio).'Z';
                    $inicio = null;
                }
            }
        }

        return $trazado;
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function medidasFavicon(float $alto): array
    {
        $favicon = $this->favicon();

        return [$alto * imagesx($favicon) / imagesy($favicon), $alto];
    }

    private function favicon(): GdImage
    {
        if ($this->favicon === null) {
            $imagen = @imagecreatefromstring($this->bytesFavicon());

            if (! $imagen instanceof GdImage) {
                throw new RuntimeException('No se pudo leer la insignia del QR.');
            }

            imagesavealpha($imagen, true);
            $this->favicon = $imagen;
        }

        return $this->favicon;
    }

    private function bytesFavicon(): string
    {
        if ($this->bytesFavicon === null) {
            $bytes = @file_get_contents(resource_path(self::RUTA_FAVICON));

            if ($bytes === false || $bytes === '') {
                throw new RuntimeException('No se encontró la insignia del QR.');
            }

            $this->bytesFavicon = $bytes;
        }

        return $this->bytesFavicon;
    }

    private function lienzo(int $ancho, int $alto): GdImage
    {
        $imagen = imagecreatetruecolor(max(1, $ancho), max(1, $alto));

        if (! $imagen instanceof GdImage) {
            throw new RuntimeException('No se pudo preparar la insignia del QR.');
        }

        imagesavealpha($imagen, true);

        return $imagen;
    }

    private function numero(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.');
    }
}
