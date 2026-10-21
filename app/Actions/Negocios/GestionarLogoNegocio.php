<?php

namespace App\Actions\Negocios;

use App\Models\Negocio;
use App\Services\Auditoria\RegistradorAuditoria;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Guarda o elimina el logotipo del negocio (usado opcionalmente al centro de
 * sus QR). La imagen se vuelve a codificar como PNG con GD: así se descartan
 * metadatos y cualquier contenido no gráfico incrustado en el archivo.
 */
class GestionarLogoNegocio
{
    private const LADO_MAXIMO = 600;

    public function __construct(private readonly RegistradorAuditoria $auditoria) {}

    public function guardar(Negocio $negocio, UploadedFile $archivo): Negocio
    {
        $imagen = @imagecreatefromstring((string) file_get_contents($archivo->getRealPath()));

        if ($imagen === false) {
            throw ValidationException::withMessages(['logo' => 'No fue posible leer la imagen.']);
        }

        $imagen = $this->redimensionar($imagen);

        ob_start();
        imagepng($imagen, null, 9);
        $png = (string) ob_get_clean();
        imagedestroy($imagen);

        $ruta = 'logos/negocio-'.$negocio->id.'-'.Str::random(16).'.png';
        Storage::disk('local')->put($ruta, $png);

        $anterior = $negocio->logo_path;
        $negocio->logo_path = $ruta;
        $negocio->save();

        if ($anterior !== null) {
            Storage::disk('local')->delete($anterior);
        }

        $this->auditoria->registrar(
            accion: 'negocio.logo_actualizado',
            descripcion: "Logotipo actualizado para «{$negocio->nombre_comercial}»",
            recurso: $negocio,
            negocio: $negocio,
        );

        return $negocio;
    }

    public function eliminar(Negocio $negocio): Negocio
    {
        if ($negocio->logo_path === null) {
            return $negocio;
        }

        Storage::disk('local')->delete($negocio->logo_path);
        $negocio->logo_path = null;
        $negocio->save();

        $this->auditoria->registrar(
            accion: 'negocio.logo_eliminado',
            descripcion: "Logotipo eliminado de «{$negocio->nombre_comercial}»",
            recurso: $negocio,
            negocio: $negocio,
        );

        return $negocio;
    }

    private function redimensionar(\GdImage $imagen): \GdImage
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $escala = min(1, self::LADO_MAXIMO / max($ancho, $alto));

        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto = max(1, (int) round($alto * $escala));

        $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        imagealphablending($destino, false);
        imagesavealpha($destino, true);
        imagefill($destino, 0, 0, (int) imagecolorallocatealpha($destino, 0, 0, 0, 127));
        imagecopyresampled($destino, $imagen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
        imagedestroy($imagen);

        return $destino;
    }
}
