<?php

namespace App\Models\Fidelidad;

use App\Enums\TipoCredencial;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tarjeta NFC, QR o enlace digital que IDENTIFICA a un consumidor. Nunca
 * autoriza visitas ni canjes por sí misma.
 *
 * @property int $id
 * @property int $negocio_id
 * @property int $consumidor_id
 * @property TipoCredencial $tipo
 * @property string $codigo
 * @property string $estado
 * @property string|null $motivo
 * @property CarbonImmutable|null $revocada_en
 * @property int|null $emitida_por
 * @property CarbonImmutable|null $created_at
 * @property-read Consumidor $consumidor
 */
class Credencial extends Model
{
    public const ACTIVA = 'activa';

    public const REVOCADA = 'revocada';

    protected $table = 'fidelidad_credenciales';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tipo' => TipoCredencial::class, 'revocada_en' => 'datetime'];
    }

    /** @return BelongsTo<Consumidor, $this> */
    public function consumidor(): BelongsTo
    {
        return $this->belongsTo(Consumidor::class, 'consumidor_id');
    }

    public function estaActiva(): bool
    {
        return $this->estado === self::ACTIVA;
    }

    public function urlPublica(): string
    {
        return route('fidelidad.publico.credencial', $this->codigo);
    }
}
