<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reembolso o contracargo detectado sobre un pago (confirmado por el
 * proveedor).
 *
 * @property int $id
 * @property int $pago_id
 * @property int|null $intento_pago_id
 * @property string $tipo
 * @property string $estado
 * @property int $importe_centavos
 * @property string|null $referencia_externa
 * @property string|null $detalle
 * @property CarbonImmutable $detectado_en
 * @property CarbonImmutable|null $resuelto_en
 * @property-read Pago $pago
 */
class AjustePago extends Model
{
    public const REEMBOLSO = 'reembolso';

    public const CONTRACARGO = 'contracargo';

    public const ABIERTO = 'abierto';

    public const APLICADO = 'aplicado';

    public const GANADO = 'ganado';

    public const PERDIDO = 'perdido';

    protected $table = 'ajustes_pago';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['importe_centavos' => 'integer', 'detectado_en' => 'datetime', 'resuelto_en' => 'datetime'];
    }

    /** @return BelongsTo<Pago, $this> */
    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    public function etiqueta(): string
    {
        return match ([$this->tipo, $this->estado]) {
            [self::REEMBOLSO, self::APLICADO] => 'Reembolso',
            [self::CONTRACARGO, self::ABIERTO] => 'Contracargo en revisión',
            [self::CONTRACARGO, self::GANADO] => 'Contracargo resuelto a favor',
            [self::CONTRACARGO, self::PERDIDO] => 'Contracargo perdido',
            default => ucfirst($this->tipo),
        };
    }
}
