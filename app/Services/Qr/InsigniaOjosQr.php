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
 * oscuro y anillo claro) y el cuadro central de 3×3 se sustituye por una
 * placa azul marino con el favicon encima.
 *
 * También dibuja el estilo elegido por el cliente: forma de los ojos
 * (cuadrado, redondeado u hoja) y de los módulos de datos (cuadrado,
 * redondeado con uniones suaves o puntos). Las esquinas redondeadas no
 * alteran la proporción 1:1:3:1:1 que buscan los lectores en las líneas que
 * cruzan el centro del ojo; se verificó la lectura con ZXing y jsQR. El
 * detector de OpenCV, que busca cuadriláteros perfectos, sí empeora con
 * esquinas redondeadas: por eso el estilo «cuadrado» sigue disponible.
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

    /** Lado máximo del lienzo sobremuestreado (memoria acotada). */
    private const LADO_MAX_LIENZO = 2400;

    /** Diámetro de cada punto (estilo «puntos»), en módulos. */
    private const DIAMETRO_PUNTO = 1.0;

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
     * Radios de las esquinas del ojo, en módulos, en el orden
     * [superior izquierda, superior derecha, inferior derecha, inferior
     * izquierda]: marco exterior, hueco claro y placa central.
     *
     * @return array{0: list<float>, 1: list<float>, 2: list<float>}
     */
    public static function radiosOjo(string $estilo): array
    {
        return match ($estilo) {
            'redondeado' => [[2.0, 2.0, 2.0, 2.0], [1.2, 1.2, 1.2, 1.2], [0.9, 0.9, 0.9, 0.9]],
            'hoja' => [[3.0, 0.5, 3.0, 0.5], [2.0, 0.3, 2.0, 0.3], [1.2, 0.3, 1.2, 0.3]],
            default => [[0.0, 0.0, 0.0, 0.0], [0.0, 0.0, 0.0, 0.0], [0.45, 0.45, 0.45, 0.45]],
        };
    }

    /**
     * Vuelve a dibujar los módulos y los ojos con el estilo elegido sobre el
     * PNG de endroid. El rectángulo del logotipo del negocio (si lo hay) se
     * deja intacto: endroid ya recortó el fondo y pegó el logo ahí.
     *
     * @param  array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}  $color
     * @param  array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}  $fondo
     * @param  array{0: int, 1: int, 2: int, 3: int}|null  $zonaLogo  x, y, ancho, alto en píxeles
     */
    public function aplicarPng(
        GdImage $imagen,
        MatrixInterface $matriz,
        array $color,
        array $fondo,
        bool $transparente,
        string $estiloOjos = 'cuadrado',
        string $estiloModulos = 'cuadrado',
        ?array $zonaLogo = null,
    ): void {
        $n = $matriz->getBlockCount();
        $bloque = (int) round($matriz->getBlockSize());
        $margen = (int) round($matriz->getMarginLeft());
        $ladoQr = $n * $bloque;

        if ($bloque < 1) {
            return;
        }

        // Se dibuja con sobremuestreo y se reduce: GD no suaviza los bordes
        // de las figuras rellenas.
        $escala = max(1, min(4, intdiv(self::LADO_MAX_LIENZO, max(1, $ladoQr))));
        $u = $bloque * $escala;

        // Celdas que tocan el logotipo: se tratan como claras.
        $omitidas = null;

        if ($zonaLogo !== null) {
            [$lx, $ly, $lw, $lh] = $zonaLogo;
            $omitidas = [
                max(0, intdiv(max(0, $lx - $margen), $bloque)),
                max(0, intdiv(max(0, $ly - $margen), $bloque)),
                min($n - 1, intdiv(max(0, $lx + $lw - $margen - 1), $bloque)),
                min($n - 1, intdiv(max(0, $ly + $lh - $margen - 1), $bloque)),
            ];
        }

        $lienzo = $this->lienzo($ladoQr * $escala, $ladoQr * $escala);
        imagealphablending($lienzo, false);
        $claro = (int) imagecolorallocatealpha($lienzo, $fondo[0], $fondo[1], $fondo[2], $transparente ? 127 : 0);
        $oscuro = (int) imagecolorallocatealpha($lienzo, $color[0], $color[1], $color[2], 0);
        $placa = (int) imagecolorallocatealpha($lienzo, self::COLOR_PLACA[0], self::COLOR_PLACA[1], self::COLOR_PLACA[2], 0);
        imagefilledrectangle($lienzo, 0, 0, $ladoQr * $escala - 1, $ladoQr * $escala - 1, $claro);

        $esOscuro = function (int $fila, int $columna) use ($matriz, $n, $omitidas): bool {
            if ($fila < 0 || $columna < 0 || $fila >= $n || $columna >= $n) {
                return false;
            }

            if ($omitidas !== null && $columna >= $omitidas[0] && $columna <= $omitidas[2] && $fila >= $omitidas[1] && $fila <= $omitidas[3]) {
                return false;
            }

            return $matriz->getBlockValue($fila, $columna) === 1 && ! self::enOjo($matriz, $fila, $columna);
        };

        for ($fila = 0; $fila < $n; $fila++) {
            for ($columna = 0; $columna < $n; $columna++) {
                if (! $esOscuro($fila, $columna)) {
                    continue;
                }

                $x = $columna * $u;
                $y = $fila * $u;

                if ($estiloModulos === 'puntos') {
                    $d = (int) round($u * self::DIAMETRO_PUNTO);
                    imagefilledellipse($lienzo, (int) round($x + $u / 2), (int) round($y + $u / 2), $d, $d, $oscuro);
                } elseif ($estiloModulos === 'redondeado') {
                    $this->rectangulo($lienzo, $x, $y, $u, $u, $this->radiosModulo($esOscuro, $fila, $columna, $u / 2), $oscuro);
                } else {
                    imagefilledrectangle($lienzo, $x, $y, $x + $u - 1, $y + $u - 1, $oscuro);
                }
            }
        }

        [$radiosMarco, $radiosHueco, $radiosPlaca] = self::radiosOjo($estiloOjos);
        $favicon = $this->favicon();
        [$anchoFav, $altoFav] = $this->medidasFavicon(self::ALTO_FAVICON * $u);
        $inicioPlaca = (self::LADO_OJO - self::LADO_PLACA) / 2 * $u;

        foreach (self::origenes($matriz) as [$columna, $fila]) {
            $x = $columna * $u;
            $y = $fila * $u;

            $this->rectangulo($lienzo, $x, $y, 7 * $u, 7 * $u, $this->escalar($radiosMarco, $u), $oscuro);
            $this->rectangulo($lienzo, $x + $u, $y + $u, 5 * $u, 5 * $u, $this->escalar($radiosHueco, $u), $claro);
            $this->rectangulo($lienzo, $x + $inicioPlaca, $y + $inicioPlaca, self::LADO_PLACA * $u, self::LADO_PLACA * $u, $this->escalar($radiosPlaca, $u), $placa);

            // El favicon se mezcla sobre la placa conservando su transparencia.
            imagealphablending($lienzo, true);
            imagecopyresampled(
                $lienzo, $favicon,
                (int) round($x + 3.5 * $u - $anchoFav / 2), (int) round($y + 3.5 * $u - $altoFav / 2),
                0, 0, (int) round($anchoFav), (int) round($altoFav), imagesx($favicon), imagesy($favicon),
            );
            imagealphablending($lienzo, false);
        }

        // Se copia sobrescribiendo (incluida la transparencia), por franjas
        // que rodean la zona del logotipo.
        imagealphablending($imagen, false);
        imagesavealpha($imagen, true);

        $copiar = function (int $dx, int $dy, int $ancho, int $alto) use ($imagen, $lienzo, $margen, $escala): void {
            if ($ancho > 0 && $alto > 0) {
                imagecopyresampled($imagen, $lienzo, $margen + $dx, $margen + $dy, $dx * $escala, $dy * $escala, $ancho, $alto, $ancho * $escala, $alto * $escala);
            }
        };

        if ($zonaLogo === null) {
            $copiar(0, 0, $ladoQr, $ladoQr);
        } else {
            // Se respeta solo el rectángulo exacto del logotipo; las celdas
            // que lo tocan quedan claras (la corrección de errores alta las
            // recupera) y no se ven restos de módulos recortados.
            $x0 = max(0, min($ladoQr, $zonaLogo[0] - $margen));
            $y0 = max(0, min($ladoQr, $zonaLogo[1] - $margen));
            $x1 = max(0, min($ladoQr, $zonaLogo[0] + $zonaLogo[2] - $margen));
            $y1 = max(0, min($ladoQr, $zonaLogo[1] + $zonaLogo[3] - $margen));
            $copiar(0, 0, $ladoQr, $y0);
            $copiar(0, $y1, $ladoQr, $ladoQr - $y1);
            $copiar(0, $y0, $x0, $y1 - $y0);
            $copiar($x1, $y0, $ladoQr - $x1, $y1 - $y0);
        }

        imagealphablending($imagen, true);
    }

    /**
     * Sustituye el trazado de módulos de endroid por el del estilo elegido y
     * dibuja los ojos con su marco, la placa y el favicon.
     *
     * @param  array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}  $color
     */
    public function aplicarSvg(SimpleXMLElement $svg, MatrixInterface $matriz, array $color, string $estiloOjos = 'cuadrado', string $estiloModulos = 'cuadrado'): void
    {
        $modulos = $svg->path[0] ?? null;

        if (! $modulos instanceof SimpleXMLElement) {
            return;
        }

        $modulos['d'] = $this->trazadoModulos($matriz, $estiloModulos);

        $hex = sprintf('#%02x%02x%02x', ...$color);
        $hexPlaca = sprintf('#%02x%02x%02x', ...self::COLOR_PLACA);
        $b = $matriz->getBlockSize();
        $m = $matriz->getMarginLeft();
        $uri = 'data:image/png;base64,'.base64_encode($this->bytesFavicon());
        [$anchoFav, $altoFav] = $this->medidasFavicon(self::ALTO_FAVICON * $b);
        $inicioPlaca = (self::LADO_OJO - self::LADO_PLACA) / 2 * $b;
        [$radiosMarco, $radiosHueco, $radiosPlaca] = self::radiosOjo($estiloOjos);

        foreach (self::origenes($matriz) as [$columna, $fila]) {
            $x = $m + $columna * $b;
            $y = $m + $fila * $b;

            $grupo = $svg->addChild('g');
            $grupo->addAttribute('class', self::CLASE_SVG);

            // Anillo oscuro: figura de 7 módulos menos el hueco de 5.
            $marco = $grupo->addChild('path');
            $marco->addAttribute('fill', $hex);
            $marco->addAttribute('fill-rule', 'evenodd');
            $marco->addAttribute('d', $this->rectanguloSvg($x, $y, 7 * $b, $this->escalar($radiosMarco, $b)).$this->rectanguloSvg($x + $b, $y + $b, 5 * $b, $this->escalar($radiosHueco, $b)));

            $placa = $grupo->addChild('path');
            $placa->addAttribute('fill', $hexPlaca);
            $placa->addAttribute('d', $this->rectanguloSvg($x + $inicioPlaca, $y + $inicioPlaca, self::LADO_PLACA * $b, $this->escalar($radiosPlaca, $b)));

            $imagen = $grupo->addChild('image');
            $imagen->addAttribute('x', $this->numero($x + 3.5 * $b - $anchoFav / 2));
            $imagen->addAttribute('y', $this->numero($y + 3.5 * $b - $altoFav / 2));
            $imagen->addAttribute('width', $this->numero($anchoFav));
            $imagen->addAttribute('height', $this->numero($altoFav));
            $imagen->addAttribute('href', $uri);
        }
    }

    /**
     * @param  list<float>  $radios  radios en módulos
     * @return list<float> radios en la unidad de dibujo
     */
    private function escalar(array $radios, float $unidad): array
    {
        return array_map(fn (float $r): float => $r * $unidad, $radios);
    }

    /**
     * Estilo «redondeado»: una esquina se redondea solo si ninguno de los dos
     * vecinos que la forman es oscuro, de modo que los módulos contiguos se
     * unen sin muescas.
     *
     * @param  callable(int, int): bool  $esOscuro
     * @return list<float>
     */
    private function radiosModulo(callable $esOscuro, int $fila, int $columna, float $radio): array
    {
        $arriba = $esOscuro($fila - 1, $columna);
        $abajo = $esOscuro($fila + 1, $columna);
        $izquierda = $esOscuro($fila, $columna - 1);
        $derecha = $esOscuro($fila, $columna + 1);

        return [
            ! $arriba && ! $izquierda ? $radio : 0.0,
            ! $arriba && ! $derecha ? $radio : 0.0,
            ! $abajo && ! $derecha ? $radio : 0.0,
            ! $abajo && ! $izquierda ? $radio : 0.0,
        ];
    }

    /**
     * Rectángulo relleno con un radio por esquina (sup. izq., sup. der.,
     * inf. der., inf. izq.).
     *
     * @param  list<float>  $radios
     */
    private function rectangulo(GdImage $imagen, float $x, float $y, float $ancho, float $alto, array $radios, int $color): void
    {
        $x1 = (int) round($x);
        $y1 = (int) round($y);
        $x2 = (int) round($x + $ancho) - 1;
        $y2 = (int) round($y + $alto) - 1;
        [$a, $b, $c, $d] = array_map(fn (float $r): int => (int) round(min($r, $ancho / 2, $alto / 2)), $radios);

        if ($a + $b + $c + $d === 0) {
            imagefilledrectangle($imagen, $x1, $y1, $x2, $y2, $color);

            return;
        }

        imagefilledpolygon($imagen, [
            $x1 + $a, $y1, $x2 - $b, $y1, $x2 - $b, $y1 + $b, $x2, $y1 + $b,
            $x2, $y2 - $c, $x2 - $c, $y2 - $c, $x2 - $c, $y2, $x1 + $d, $y2,
            $x1 + $d, $y2 - $d, $x1, $y2 - $d, $x1, $y1 + $a, $x1 + $a, $y1 + $a,
        ], $color);

        foreach ([[$a, $x1 + $a, $y1 + $a], [$b, $x2 - $b, $y1 + $b], [$c, $x2 - $c, $y2 - $c], [$d, $x1 + $d, $y2 - $d]] as [$r, $cx, $cy]) {
            if ($r > 0) {
                imagefilledellipse($imagen, $cx, $cy, 2 * $r + 1, 2 * $r + 1, $color);
            }
        }
    }

    /**
     * @param  list<float>  $radios
     */
    private function rectanguloSvg(float $x, float $y, float $lado, array $radios): string
    {
        [$a, $b, $c, $d] = array_map(fn (float $r): float => min($r, $lado / 2), $radios);
        $f = fn (float $v): string => $this->numero($v);
        $arco = fn (float $r, float $ax, float $ay): string => $r > 0
            ? 'A'.$f($r).','.$f($r).' 0 0 1 '.$f($ax).','.$f($ay)
            : 'L'.$f($ax).','.$f($ay);

        return 'M'.$f($x + $a).','.$f($y)
            .'H'.$f($x + $lado - $b).$arco($b, $x + $lado, $y + $b)
            .'V'.$f($y + $lado - $c).$arco($c, $x + $lado - $c, $y + $lado)
            .'H'.$f($x + $d).$arco($d, $x, $y + $lado - $d)
            .'V'.$f($y + $a).$arco($a, $x + $a, $y).'Z';
    }

    /**
     * Trazado de los módulos de datos (sin los ojos) en el estilo elegido.
     */
    private function trazadoModulos(MatrixInterface $matriz, string $estilo): string
    {
        if ($estilo === 'cuadrado') {
            return $this->trazadoSinOjos($matriz);
        }

        $n = $matriz->getBlockCount();
        $b = $matriz->getBlockSize();
        $m = $matriz->getMarginLeft();
        $esOscuro = fn (int $fila, int $columna): bool => $fila >= 0 && $columna >= 0 && $fila < $n && $columna < $n
            && $matriz->getBlockValue($fila, $columna) === 1 && ! self::enOjo($matriz, $fila, $columna);
        $trazado = '';

        for ($fila = 0; $fila < $n; $fila++) {
            for ($columna = 0; $columna < $n; $columna++) {
                if (! $esOscuro($fila, $columna)) {
                    continue;
                }

                $x = $m + $columna * $b;
                $y = $m + $fila * $b;

                if ($estilo === 'puntos') {
                    $r = $b * self::DIAMETRO_PUNTO / 2;
                    $trazado .= 'M'.$this->numero($x + $b / 2 - $r).','.$this->numero($y + $b / 2)
                        .'a'.$this->numero($r).','.$this->numero($r).' 0 1 0 '.$this->numero(2 * $r).',0'
                        .'a'.$this->numero($r).','.$this->numero($r).' 0 1 0 '.$this->numero(-2 * $r).',0Z';
                } else {
                    $trazado .= $this->rectanguloSvg($x, $y, $b, $this->radiosModulo($esOscuro, $fila, $columna, $b / 2));
                }
            }
        }

        return $trazado;
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
