<?php

namespace App\Services\Medios;

/**
 * Límites REALES de carga de archivos, en un solo lugar para el servidor y
 * la interfaz.
 *
 * El límite efectivo de cada tipo es el menor entre el configurado por DASTI
 * (config/dasti.php → medios) y lo que PHP acepta en este servidor
 * (upload_max_filesize y post_max_size). Así la interfaz nunca anuncia un
 * tamaño que el servidor rechazaría. Nginx debe permitir al menos
 * post_max_size (client_max_body_size); ver docs/despliegue.md.
 */
class LimitesArchivos
{
    public const LOGO = 'logo';

    public const IMAGEN = 'imagen';

    public const VIDEO = 'video';

    /**
     * Margen para los demás campos del formulario multipart.
     */
    private const MARGEN_FORMULARIO = 64 * 1024;

    private const TIPOS = [
        self::LOGO => [
            'config' => 'dasti.medios.logo_max_kb',
            'predeterminado' => 5120,
            'formatos' => ['PNG', 'JPG', 'WEBP'],
            'extensiones' => ['png', 'jpg', 'jpeg', 'webp'],
            'mimes' => ['image/png', 'image/jpeg', 'image/webp'],
            'recomendacion' => 'Cuadrado o rectangular, de al menos 256 × 256 px, de preferencia con fondo transparente (PNG o WEBP).',
        ],
        self::IMAGEN => [
            'config' => 'dasti.medios.imagen_max_kb',
            'predeterminado' => 10240,
            'formatos' => ['PNG', 'JPG', 'WEBP', 'GIF'],
            'extensiones' => ['png', 'jpg', 'jpeg', 'webp', 'gif'],
            'mimes' => ['image/png', 'image/jpeg', 'image/webp', 'image/gif'],
            'recomendacion' => 'Fotografías, fondos y portadas de al menos 1600 px de ancho. Se optimizan automáticamente para celular y computadora.',
        ],
        self::VIDEO => [
            'config' => 'dasti.medios.video_max_kb',
            'predeterminado' => 20480,
            'formatos' => ['MP4', 'WEBM'],
            'extensiones' => ['mp4', 'webm'],
            'mimes' => ['video/mp4', 'video/webm'],
            'recomendacion' => 'Video corto en bucle (10 a 20 segundos), sin sonido, de 1280 px de ancho como máximo.',
        ],
    ];

    /**
     * Tamaño máximo efectivo en bytes.
     */
    public function bytes(string $tipo): int
    {
        $definicion = self::TIPOS[$tipo] ?? self::TIPOS[self::IMAGEN];
        $configurado = max(1, (int) config($definicion['config'], $definicion['predeterminado'])) * 1024;

        return min($configurado, $this->servidor());
    }

    /**
     * Lo que PHP acepta en una carga (bytes).
     */
    public function servidor(): int
    {
        $subida = self::aBytes((string) ini_get('upload_max_filesize'));
        $post = self::aBytes((string) ini_get('post_max_size'));
        $limites = array_filter([$subida, $post > 0 ? max(1, $post - self::MARGEN_FORMULARIO) : 0]);

        return $limites === [] ? PHP_INT_MAX : min($limites);
    }

    /**
     * Texto «PNG, JPG o WEBP. Tamaño máximo: 10 MB».
     */
    public function texto(string $tipo): string
    {
        $d = $this->descripcion($tipo);

        return "{$d['formatos_texto']}. Tamaño máximo: {$d['max_texto']}.";
    }

    /**
     * @return array{tipo: string, max_bytes: int, max_texto: string, formatos: list<string>, formatos_texto: string, extensiones: list<string>, mimes: list<string>, aceptar: string, recomendacion: string}
     */
    public function descripcion(string $tipo): array
    {
        $tipo = isset(self::TIPOS[$tipo]) ? $tipo : self::IMAGEN;
        $d = self::TIPOS[$tipo];
        // «PNG, JPG o WEBP»: todos los tipos tienen al menos dos formatos.
        $formatos = $d['formatos'];
        $ultimo = array_pop($formatos);

        return [
            'tipo' => $tipo,
            'max_bytes' => $this->bytes($tipo),
            'max_texto' => self::legible($this->bytes($tipo)),
            'formatos' => $d['formatos'],
            'formatos_texto' => implode(', ', $formatos).' o '.$ultimo,
            'extensiones' => $d['extensiones'],
            'mimes' => $d['mimes'],
            'aceptar' => implode(',', [...$d['mimes'], ...array_map(fn ($e) => '.'.$e, $d['extensiones'])]),
            'recomendacion' => $d['recomendacion'],
        ];
    }

    /**
     * Todos los límites (se comparten con la interfaz).
     *
     * @return array<string, array<string, mixed>>
     */
    public function todos(): array
    {
        return array_map(fn (string $t) => $this->descripcion($t), array_combine(array_keys(self::TIPOS), array_keys(self::TIPOS)));
    }

    /**
     * Mensaje cuando el cuerpo de la petición supera lo que acepta PHP.
     */
    public function mensajeExcedido(): string
    {
        return 'El archivo es demasiado grande. El máximo que acepta el servidor es '.self::legible($this->servidor()).'. Elige un archivo más ligero.';
    }

    public static function legible(int $bytes): string
    {
        if ($bytes >= 1048576) {
            $mb = $bytes / 1048576;

            return (fmod($mb, 1.0) < 0.05 ? number_format($mb, 0) : number_format($mb, 1)).' MB';
        }

        return max(1, (int) round($bytes / 1024)).' KB';
    }

    public static function aBytes(string $valor): int
    {
        $valor = trim($valor);

        if ($valor === '' || $valor === '-1' || $valor === '0') {
            return 0;
        }

        $numero = (float) $valor;

        return (int) match (strtolower(substr($valor, -1))) {
            'g' => $numero * 1073741824,
            'm' => $numero * 1048576,
            'k' => $numero * 1024,
            default => $numero,
        };
    }
}
