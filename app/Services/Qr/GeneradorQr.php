<?php

namespace App\Services\Qr;

use App\Models\Negocio;
use App\Models\Producto;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\SvgResult;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleXMLElement;
use Throwable;

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

    private const ID_MASCARA_LOGO = 'recorte-logo';

    /**
     * @return array{contenido: string, mime: string, nombre_archivo: string}
     *
     * @throws ErrorGeneracionQr si el logotipo no puede procesarse o la
     *                           biblioteca no logra construir el código.
     */
    public function generar(Producto $producto, OpcionesQr $opciones, string $formato = 'png', int $tamano = 1024): array
    {
        $formato = $formato === 'svg' ? 'svg' : 'png';
        $tamano = in_array($tamano, OpcionesQr::TAMANOS, true) ? $tamano : 1024;

        $logo = $opciones->incluirLogo ? $this->rutaLogo($producto->negocio) : null;

        if ($opciones->incluirLogo && $logo === null) {
            throw new ErrorGeneracionQr('El logotipo del negocio no está disponible. Vuelve a cargarlo o desactiva «Logotipo al centro».');
        }

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
            // El escritor SVG de endroid no admite el recorte del fondo; en
            // SVG se aplica con una máscara equivalente (recortarBajoLogo).
            logoPunchoutBackground: $logo !== null && $formato === 'png',
        );

        try {
            $resultado = $builder->build();
        } catch (Throwable $e) {
            Log::warning('No se pudo generar un QR', ['producto_id' => $producto->id, 'error' => $e->getMessage()]);

            throw new ErrorGeneracionQr(
                $logo !== null
                    ? 'No se pudo procesar el logotipo del negocio. Vuelve a cargarlo o desactiva «Logotipo al centro».'
                    : 'No se pudo generar el código QR. Inténtalo de nuevo.',
                previous: $e,
            );
        }

        if ($logo !== null && $resultado instanceof SvgResult) {
            $this->recortarBajoLogo($resultado);
        }

        return [
            'contenido' => $resultado->getString(),
            'mime' => $resultado->getMimeType(),
            'nombre_archivo' => 'qr-'.Str::slug($producto->nombre).'-'.$producto->codigo.'.'.$formato,
        ];
    }

    /**
     * Indica si el negocio tiene un logotipo legible para incrustar en el QR.
     */
    public function logoDisponible(Negocio $negocio): bool
    {
        return $this->rutaLogo($negocio) !== null;
    }

    private function rutaLogo(Negocio $negocio): ?string
    {
        $ruta = $negocio->logo_path;

        if ($ruta === null || ! Storage::disk('local')->exists($ruta)) {
            return null;
        }

        $absoluta = Storage::disk('local')->path($ruta);

        // Descarta archivos dañados antes de entregarlos a la biblioteca.
        return @getimagesize($absoluta) !== false ? $absoluta : null;
    }

    /**
     * Equivalente SVG del recorte de fondo del escritor PNG: una máscara
     * elimina los módulos que quedan bajo el logotipo, de modo que el logo
     * no se superpone a ellos (con fondo de color o transparente).
     */
    private function recortarBajoLogo(SvgResult $resultado): void
    {
        $svg = $resultado->getXml();
        $imagen = $svg->image[0] ?? null;
        $modulos = $svg->path[0] ?? null;

        if (! $imagen instanceof SimpleXMLElement || ! $modulos instanceof SimpleXMLElement) {
            return;
        }

        $vista = explode(' ', (string) $svg['viewBox']);
        $lado = (string) ($vista[2] ?? '0');

        $mascara = $svg->addChild('defs')->addChild('mask');
        $mascara->addAttribute('id', self::ID_MASCARA_LOGO);
        $mascara->addAttribute('maskUnits', 'userSpaceOnUse');

        $visible = $mascara->addChild('rect');
        $visible->addAttribute('x', '0');
        $visible->addAttribute('y', '0');
        $visible->addAttribute('width', $lado);
        $visible->addAttribute('height', $lado);
        $visible->addAttribute('fill', '#ffffff');

        $oculto = $mascara->addChild('rect');
        $oculto->addAttribute('x', (string) $imagen['x']);
        $oculto->addAttribute('y', (string) $imagen['y']);
        $oculto->addAttribute('width', (string) $imagen['width']);
        $oculto->addAttribute('height', (string) $imagen['height']);
        $oculto->addAttribute('fill', '#000000');

        $modulos->addAttribute('mask', 'url(#'.self::ID_MASCARA_LOGO.')');
    }
}
