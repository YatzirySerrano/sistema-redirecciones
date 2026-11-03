<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Imagen o video que un negocio publica en su Página de enlaces o en su
 * tarjeta de Fidelidad. Se guarda en el disco local (no público) y se sirve
 * por /medios/{uuid}.{extension} con cabeceras seguras. Las imágenes se
 * re-codifican (sin metadatos); se guarda solo el resultado.
 *
 * @property int $id
 * @property string $uuid
 * @property int $negocio_id
 * @property string $modulo
 * @property string $tipo
 * @property string $ruta
 * @property string $mime
 * @property string $extension
 * @property int $bytes
 * @property int|null $ancho
 * @property int|null $alto
 * @property string $hash
 * @property string|null $nombre_original
 * @property int|null $subido_por
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Negocio $negocio
 */
class ArchivoPublico extends Model
{
    public const TIPO_IMAGEN = 'imagen';

    public const TIPO_VIDEO = 'video';

    public const MODULO_PAGINAS = 'paginas';

    public const MODULO_FIDELIDAD = 'fidelidad';

    protected $table = 'archivos_publicos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['bytes' => 'integer', 'ancho' => 'integer', 'alto' => 'integer'];
    }

    /** @return BelongsTo<Negocio, $this> */
    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class);
    }

    /**
     * URL relativa (funciona igual en el dominio de DASTI y en un dominio
     * personalizado del negocio).
     */
    public function url(): string
    {
        return '/medios/'.$this->uuid.'.'.$this->extension;
    }

    /**
     * @return array{id: string, url: string, tipo: string, ancho: int|null, alto: int|null, bytes: int}
     */
    public function resumen(): array
    {
        return [
            'id' => $this->uuid,
            'url' => $this->url(),
            'tipo' => $this->tipo,
            'ancho' => $this->ancho,
            'alto' => $this->alto,
            'bytes' => $this->bytes,
        ];
    }
}
