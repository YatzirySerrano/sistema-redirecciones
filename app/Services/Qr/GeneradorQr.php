<?php

namespace App\Services\Qr;

use App\Models\Producto;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Genera el QR de un producto con su URL permanente.
 *
 * El QR nunca incluye marca de DASTI: solo el código y, si se solicita y el
 * negocio cargó uno, el logotipo del propio negocio.
 */
class GeneradorQr
{
    // Proporción del ancho que ocupa el logotipo. Con corrección de errores
    // alta (~30 %) un logo del 20 % de ancho cubre ~4 % del área.
    private const PROPORCION_LOGO = 0.2;

    /**
     * @return array{contenido: string, mime: string, nombre_archivo: string}
     */
    public function generar(Producto $producto, OpcionesQr $opciones, string $formato = 'png', int $tamano = 1024): array
    {
        $formato = $formato === 'svg' ? 'svg' : 'png';
        $tamano = in_array($tamano, OpcionesQr::TAMANOS, true) ? $tamano : 1024;

        $logo = $opciones->incluirLogo ? $this->rutaLogo($producto) : null;
        [$r, $g, $b] = OpcionesQr::rgb($opciones->color);
        [$fr, $fg, $fb] = OpcionesQr::rgb($opciones->fondo);

        $builder = new Builder(
            writer: $formato === 'svg' ? new SvgWriter : new PngWriter,
            data: $producto->url_permanente,
            errorCorrectionLevel: $logo !== null ? ErrorCorrectionLevel::High : ErrorCorrectionLevel::Medium,
            size: $tamano,
            margin: (int) round($tamano * OpcionesQr::MARGENES[$opciones->margen]),
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color($r, $g, $b),
            backgroundColor: new Color($fr, $fg, $fb, $opciones->fondoTransparente ? 127 : 0),
            logoPath: $logo ?? '',
            logoResizeToWidth: $logo !== null ? (int) round($tamano * self::PROPORCION_LOGO) : null,
            logoPunchoutBackground: $logo !== null,
        );

        $resultado = $builder->build();

        return [
            'contenido' => $resultado->getString(),
            'mime' => $resultado->getMimeType(),
            'nombre_archivo' => 'qr-'.Str::slug($producto->nombre).'-'.$producto->codigo.'.'.$formato,
        ];
    }

    private function rutaLogo(Producto $producto): ?string
    {
        $ruta = $producto->negocio->logo_path;

        if ($ruta === null || ! Storage::disk('local')->exists($ruta)) {
            return null;
        }

        return Storage::disk('local')->path($ruta);
    }
}
