<?php

namespace App\Services\Medios;

use App\Models\ArchivoPublico;
use App\Models\Negocio;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Almacén de imágenes y videos publicados por los negocios (Página de
 * enlaces y tarjeta de Fidelidad). Compartido por ambos módulos.
 *
 * Seguridad:
 * - El tipo se determina por el CONTENIDO (finfo + decodificación con GD),
 *   nunca por la extensión ni por el tipo que declara el navegador.
 * - Las imágenes se decodifican y se vuelven a codificar en WEBP (o PNG si
 *   el servidor no admite WEBP): se descartan metadatos (EXIF, GPS),
 *   perfiles y cualquier contenido no gráfico. SVG no se admite (puede
 *   contener scripts).
 * - Límites de tamaño, dimensiones y cuota por negocio y módulo.
 * - Se guardan en el disco local (no público) con nombre aleatorio; se
 *   sirven por MedioController con `nosniff` y CSP restrictiva.
 */
class AlmacenMedios
{
    private const MIMES_IMAGEN = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];

    private const MIMES_VIDEO = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];

    public function __construct(
        private readonly RegistradorAuditoria $auditoria,
        private readonly LimitesArchivos $limites,
    ) {}

    /**
     * @param  int  $ladoMaximo  Lado mayor resultante en píxeles.
     * @param  string  $limite  LimitesArchivos::LOGO o ::IMAGEN.
     */
    public function guardarImagen(UploadedFile $archivo, Negocio $negocio, string $modulo, ?User $usuario, int $ladoMaximo = 1600, string $campo = 'archivo', string $limite = LimitesArchivos::IMAGEN): ArchivoPublico
    {
        $maxBytes = $this->limites->bytes($limite);
        $ruta = $archivo->getRealPath();

        if ($ruta === false || ! $archivo->isValid()) {
            throw ValidationException::withMessages([$campo => $this->errorSubida($archivo, $limite)]);
        }

        if ($archivo->getSize() > $maxBytes) {
            throw ValidationException::withMessages([$campo => 'La imagen pesa '.LimitesArchivos::legible((int) $archivo->getSize()).'. '.$this->limites->texto($limite)]);
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($ruta);
        $info = @getimagesize($ruta);

        if (! in_array($mime, self::MIMES_IMAGEN, true) || $info === false || $info[0] < 1 || $info[1] < 1) {
            throw ValidationException::withMessages([$campo => 'El archivo no es una imagen válida. Usa PNG, JPG, WEBP o GIF.']);
        }

        // Evita «bombas» de descompresión: dimensiones absurdas.
        if ($info[0] * $info[1] > 40_000_000) {
            throw ValidationException::withMessages([$campo => 'La imagen tiene dimensiones demasiado grandes. Usa una de menos de 40 megapíxeles.']);
        }

        $this->verificarCuota($negocio, $modulo, $campo);

        // Decodificar una foto grande requiere memoria (≈ 5 bytes por píxel):
        // se amplía solo durante el procesamiento.
        $this->asegurarMemoria($info[0] * $info[1]);

        $contenido = @file_get_contents($ruta);
        $imagen = is_string($contenido) && $contenido !== '' ? @imagecreatefromstring($contenido) : false;

        if ($imagen === false) {
            throw ValidationException::withMessages([$campo => 'No pudimos leer la imagen: el archivo parece dañado.']);
        }

        $imagen = $this->orientar($imagen, $mime, $ruta);
        $imagen = $this->redimensionar($imagen, $ladoMaximo);
        [$binario, $mimeFinal, $extension] = $this->codificar($imagen);
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        imagedestroy($imagen);

        return $this->persistir($negocio, $modulo, ArchivoPublico::TIPO_IMAGEN, $binario, $mimeFinal, $extension, $ancho, $alto, $archivo, $usuario, $campo);
    }

    /**
     * Video de fondo corto (MP4/WEBM). No se re-codifica (el servidor no
     * dispone de un transcodificador); se valida el contenedor real y el
     * tamaño. La interfaz pública lo reproduce sin sonido, respeta
     * «reducir movimiento» y ofrece una imagen alternativa.
     */
    public function guardarVideo(UploadedFile $archivo, Negocio $negocio, string $modulo, ?User $usuario, string $campo = 'archivo'): ArchivoPublico
    {
        $maxBytes = $this->limites->bytes(LimitesArchivos::VIDEO);
        $ruta = $archivo->getRealPath();

        if ($ruta === false || ! $archivo->isValid()) {
            throw ValidationException::withMessages([$campo => $this->errorSubida($archivo, LimitesArchivos::VIDEO)]);
        }

        if ($archivo->getSize() > $maxBytes) {
            throw ValidationException::withMessages([$campo => 'El video pesa '.LimitesArchivos::legible((int) $archivo->getSize()).'. '.$this->limites->texto(LimitesArchivos::VIDEO)]);
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($ruta);

        if (! isset(self::MIMES_VIDEO[$mime])) {
            throw ValidationException::withMessages([$campo => 'El archivo no es un video válido. Usa MP4 o WEBM.']);
        }

        $this->verificarCuota($negocio, $modulo, $campo);
        $binario = (string) file_get_contents($ruta);

        return $this->persistir($negocio, $modulo, ArchivoPublico::TIPO_VIDEO, $binario, $mime, self::MIMES_VIDEO[$mime], null, null, $archivo, $usuario, $campo);
    }

    /**
     * Mensaje comprensible cuando PHP no pudo recibir el archivo (p. ej.
     * supera upload_max_filesize).
     */
    private function errorSubida(UploadedFile $archivo, string $limite): string
    {
        return in_array($archivo->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'El archivo es demasiado grande. '.$this->limites->texto($limite)
            : 'No recibimos el archivo completo. Inténtalo nuevamente.';
    }

    private function asegurarMemoria(int $pixeles): void
    {
        $necesaria = 64 * 1048576 + $pixeles * 6;
        $actual = LimitesArchivos::aBytes((string) ini_get('memory_limit'));

        if ($actual > 0 && $actual < $necesaria) {
            @ini_set('memory_limit', (string) min($necesaria, 768 * 1048576));
        }
    }

    /**
     * Archivo del negocio y módulo indicados (nunca de otro negocio).
     */
    public function delNegocio(Negocio $negocio, string $modulo, ?string $uuid, ?string $tipo = null): ?ArchivoPublico
    {
        if ($uuid === null || ! Str::isUuid($uuid)) {
            return null;
        }

        return ArchivoPublico::query()
            ->where('uuid', $uuid)
            ->where('negocio_id', $negocio->id)
            ->where('modulo', $modulo)
            ->when($tipo, fn ($q, string $t) => $q->where('tipo', $t))
            ->first();
    }

    public function eliminar(ArchivoPublico $archivo, ?User $usuario = null): void
    {
        Storage::disk('local')->delete($archivo->ruta);
        $archivo->delete();

        $this->auditoria->registrar(
            accion: 'medio.eliminado',
            descripcion: 'Archivo publicado eliminado',
            recurso: $archivo,
            negocio: $archivo->negocio_id,
            anteriores: ['modulo' => $archivo->modulo, 'tipo' => $archivo->tipo],
            actor: $usuario,
        );
    }

    /**
     * @return array{usados: int, bytes: int, max_archivos: int, max_bytes: int}
     */
    public function uso(Negocio $negocio, string $modulo): array
    {
        $consulta = ArchivoPublico::query()->where('negocio_id', $negocio->id)->where('modulo', $modulo);

        return [
            'usados' => (clone $consulta)->count(),
            'bytes' => (int) (clone $consulta)->sum('bytes'),
            'max_archivos' => (int) config('dasti.medios.max_archivos', 300),
            'max_bytes' => (int) config('dasti.medios.cuota_mb', 250) * 1048576,
        ];
    }

    private function verificarCuota(Negocio $negocio, string $modulo, string $campo): void
    {
        $uso = $this->uso($negocio, $modulo);

        if ($uso['usados'] >= $uso['max_archivos'] || $uso['bytes'] >= $uso['max_bytes']) {
            throw ValidationException::withMessages([$campo => 'Alcanzaste el espacio disponible para archivos. Elimina imágenes que ya no uses e inténtalo de nuevo.']);
        }
    }

    private function persistir(Negocio $negocio, string $modulo, string $tipo, string $binario, string $mime, string $extension, ?int $ancho, ?int $alto, UploadedFile $original, ?User $usuario, string $campo): ArchivoPublico
    {
        $uuid = (string) Str::uuid();
        $ruta = "medios/{$negocio->id}/{$modulo}/{$uuid}.{$extension}";

        try {
            $guardado = $binario !== '' && Storage::disk('local')->put($ruta, $binario);
        } catch (Throwable $e) {
            report($e);
            $guardado = false;
        }

        if (! $guardado) {
            throw ValidationException::withMessages([$campo => 'No pudimos guardar el archivo en el servidor. Inténtalo nuevamente.']);
        }

        $nombre = mb_substr((string) preg_replace('/[^\pL\pN ._-]/u', '', $original->getClientOriginalName()), 0, 120);

        $archivo = ArchivoPublico::query()->create([
            'uuid' => $uuid,
            'negocio_id' => $negocio->id,
            'modulo' => $modulo,
            'tipo' => $tipo,
            'ruta' => $ruta,
            'mime' => $mime,
            'extension' => $extension,
            'bytes' => strlen($binario),
            'ancho' => $ancho,
            'alto' => $alto,
            'hash' => hash('sha256', $binario),
            'nombre_original' => $nombre !== '' ? $nombre : null,
            'subido_por' => $usuario?->id,
        ]);

        $this->auditoria->registrar(
            accion: 'medio.subido',
            descripcion: $tipo === ArchivoPublico::TIPO_VIDEO ? 'Video subido para publicación' : 'Imagen subida para publicación',
            recurso: $archivo,
            negocio: $negocio,
            nuevos: ['modulo' => $modulo, 'bytes' => $archivo->bytes],
            actor: $usuario,
        );

        return $archivo;
    }

    /**
     * Aplica la orientación EXIF de las fotos de teléfono antes de
     * descartar los metadatos.
     */
    private function orientar(GdImage $imagen, string $mime, string $ruta): GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $imagen;
        }

        $exif = @exif_read_data($ruta);
        $grados = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($grados === 0) {
            return $imagen;
        }

        $rotada = imagerotate($imagen, $grados, 0);

        if ($rotada === false) {
            return $imagen;
        }

        imagedestroy($imagen);

        return $rotada;
    }

    private function redimensionar(GdImage $imagen, int $ladoMaximo): GdImage
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $escala = min(1, $ladoMaximo / max($ancho, $alto));
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

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function codificar(GdImage $imagen): array
    {
        ob_start();

        if (function_exists('imagewebp') && imagewebp($imagen, null, 84)) {
            return [(string) ob_get_clean(), 'image/webp', 'webp'];
        }

        imagepng($imagen, null, 8);

        return [(string) ob_get_clean(), 'image/png', 'png'];
    }
}
