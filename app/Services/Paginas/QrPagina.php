<?php

namespace App\Services\Paginas;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * QR de la dirección pública de una Página de enlaces (sin marca de DASTI).
 */
class QrPagina
{
    /**
     * @return array{contenido: string, mime: string}
     */
    public function generar(string $url, string $formato = 'svg', int $tamano = 1024, string $color = '#111111', string $fondo = '#ffffff'): array
    {
        $formato = $formato === 'png' ? 'png' : 'svg';
        $tamano = max(128, min(2048, $tamano));
        $canal = fn (string $hex, int $i) => max(0, min(255, (int) hexdec(substr($hex, 1 + $i * 2, 2))));
        $color = preg_match('/^#[0-9a-f]{6}$/i', $color) ? $color : '#111111';
        $fondo = preg_match('/^#[0-9a-f]{6}$/i', $fondo) ? $fondo : '#ffffff';

        $resultado = (new Builder(
            writer: $formato === 'svg' ? new SvgWriter : new PngWriter,
            data: $url,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $tamano,
            margin: (int) round($tamano * 0.06),
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color($canal($color, 0), $canal($color, 1), $canal($color, 2)),
            backgroundColor: new Color($canal($fondo, 0), $canal($fondo, 1), $canal($fondo, 2)),
        ))->build();

        return ['contenido' => $resultado->getString(), 'mime' => $resultado->getMimeType()];
    }
}
