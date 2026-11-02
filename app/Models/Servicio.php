<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio comercial de DASTI (catálogo). El precio lo configura DASTI; sin
 * precio no se puede contratar en línea.
 *
 * @property int $id
 * @property string $clave
 * @property string $nombre
 * @property string|null $descripcion
 * @property bool $es_base
 * @property int|null $precio_centavos
 * @property string $moneda
 * @property int|null $vigencia_meses
 * @property bool $contratable_en_linea
 * @property bool $activo
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Servicio extends Model
{
    public const FIDELIDAD = 'fidelidad';

    public const REDIRECCIONES = 'redirecciones';

    protected $table = 'servicios';

    protected $guarded = ['id', 'clave'];

    protected function casts(): array
    {
        return [
            'es_base' => 'boolean',
            'precio_centavos' => 'integer',
            'vigencia_meses' => 'integer',
            'contratable_en_linea' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public static function fidelidad(): self
    {
        return self::query()->where('clave', self::FIDELIDAD)->firstOrFail();
    }

    /**
     * Motivo por el que no se puede contratar en línea, o null si sí.
     */
    public function motivoNoContratable(): ?string
    {
        return match (true) {
            ! $this->activo => 'El servicio no está disponible por ahora.',
            $this->precio_centavos === null || $this->precio_centavos <= 0 => 'DASTI aún no ha configurado el precio de este servicio.',
            ! $this->contratable_en_linea => 'La contratación en línea no está habilitada; contacta a DASTI.',
            default => null,
        };
    }
}
