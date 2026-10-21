<?php

namespace Tests\Concerns;

use Throwable;
use Zxing\Qrcode\Decoder\Decoder;
use Zxing\QrReader;

/**
 * Decodifica en las pruebas los PNG que genera la aplicación.
 *
 * 1. Lectura normal con detección del patrón (khanamiryan/qrcode-detector-
 *    decoder, puerto PHP de ZXing).
 * 2. Ese detector no localiza el patrón en ~3 % de los códigos válidos
 *    generados al azar (se verificó que otro decodificador independiente,
 *    jsQR, sí los lee). Como las imágenes generadas son limpias y sin
 *    perspectiva, en ese caso se muestrea la rejilla de módulos y se entrega
 *    directamente al decodificador de ZXing, que aplica la corrección de
 *    errores Reed-Solomon. El texto debe coincidir siempre de forma exacta.
 */
trait LeeCodigosQr
{
    protected function leerQr(string $png): ?string
    {
        $texto = (new QrReader($png, QrReader::SOURCE_TYPE_BLOB, false))->text();

        if (is_string($texto) && $texto !== '') {
            return $texto;
        }

        return $this->leerRejillaQr($png);
    }

    private function leerRejillaQr(string $png): ?string
    {
        $imagen = imagecreatefromstring($png);
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);

        // Luminancia relativa (0 oscuro … 1 claro); lo transparente es claro.
        $luz = function (int $x, int $y) use ($imagen): float {
            $c = imagecolorat($imagen, $x, $y);

            if ((($c >> 24) & 0x7F) >= 64) {
                return 1.0;
            }

            return (0.2126 * (($c >> 16) & 255) + 0.7152 * (($c >> 8) & 255) + 0.0722 * ($c & 255)) / 255;
        };

        [$minimo, $maximo] = [1.0, 0.0];
        for ($y = 0; $y < $alto; $y += 2) {
            for ($x = 0; $x < $ancho; $x += 2) {
                $minimo = min($minimo, $luz($x, $y));
                $maximo = max($maximo, $luz($x, $y));
            }
        }
        $umbral = ($minimo + $maximo) / 2;
        $oscuro = fn (int $x, int $y): bool => $luz($x, $y) < $umbral;

        // Esquina superior izquierda del patrón de localización.
        [$x0, $y0] = [null, null];
        for ($y = 0; $y < $alto && $x0 === null; $y++) {
            for ($x = 0; $x < $ancho; $x++) {
                if ($oscuro($x, $y)) {
                    [$x0, $y0] = [$x, $y];
                    break;
                }
            }
        }

        if ($x0 === null) {
            return null;
        }

        // El patrón mide 7 módulos; el último píxel oscuro de la fila marca el
        // borde derecho del código.
        $tramo = 0;
        while ($x0 + $tramo < $ancho && $oscuro($x0 + $tramo, $y0)) {
            $tramo++;
        }
        $x1 = $ancho - 1;
        while ($x1 > $x0 && ! $oscuro($x1, $y0)) {
            $x1--;
        }

        $lado = $x1 - $x0 + 1;
        $modulos = (int) round($lado / ($tramo / 7));

        if ($modulos < 21 || ($modulos - 17) % 4 !== 0) {
            return null;
        }

        $paso = $lado / $modulos;
        $matriz = [];
        for ($i = 0; $i < $modulos; $i++) {
            for ($j = 0; $j < $modulos; $j++) {
                $matriz[$i][$j] = $oscuro((int) ($x0 + ($j + 0.5) * $paso), (int) ($y0 + ($i + 0.5) * $paso));
            }
        }

        try {
            $resultado = (new Decoder)->decodeImage($matriz);

            return is_string($resultado) ? $resultado : $resultado->getText();
        } catch (Throwable) {
            return null;
        }
    }
}
