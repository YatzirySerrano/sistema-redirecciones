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

        // Contorno del código: funciona con ojos de esquinas rectas o
        // redondeadas, porque los bordes rectos de los ojos marcan los límites.
        [$x0, $y0, $x1, $y1] = [$ancho, $alto, -1, -1];
        for ($y = 0; $y < $alto; $y++) {
            for ($x = 0; $x < $ancho; $x++) {
                if ($oscuro($x, $y)) {
                    $x0 = min($x0, $x);
                    $y0 = min($y0, $y);
                    $x1 = max($x1, $x);
                    $y1 = max($y1, $y);
                }
            }
        }

        if ($x1 < 0) {
            return null;
        }

        $lado = (($x1 - $x0) + ($y1 - $y0)) / 2 + 1;

        // Se prueba cada versión posible (21, 25, … módulos) y se queda con la
        // primera rejilla que decodifica.
        for ($modulos = 21; $modulos <= 177; $modulos += 4) {
            $paso = $lado / $modulos;

            if ($paso < 1.5) {
                break;
            }

            $matriz = [];
            for ($i = 0; $i < $modulos; $i++) {
                for ($j = 0; $j < $modulos; $j++) {
                    $matriz[$i][$j] = $oscuro((int) ($x0 + ($j + 0.5) * $paso), (int) ($y0 + ($i + 0.5) * $paso));
                }
            }

            $texto = $this->decodificarRejilla($matriz);

            if ($texto !== null) {
                return $texto;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<int, bool>>  $matriz
     */
    private function decodificarRejilla(array $matriz): ?string
    {
        try {
            $resultado = (new Decoder)->decodeImage($matriz);

            return is_string($resultado) ? $resultado : $resultado->getText();
        } catch (Throwable) {
            return null;
        }
    }
}
