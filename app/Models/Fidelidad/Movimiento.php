<?php

namespace App\Models\Fidelidad;

use App\Enums\TipoMovimiento;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimiento inmutable del saldo de visitas (visita, ajuste o anulación).
 *
 * @property int $id
 * @property int $negocio_id
 * @property int $participacion_id
 * @property int $consumidor_id
 * @property int|null $credencial_id
 * @property TipoMovimiento $tipo
 * @property int $cantidad
 * @property int $ciclo
 * @property string $operacion_uuid
 * @property string|null $motivo
 * @property int|null $autorizado_por
 * @property CarbonImmutable $created_at
 * @property-read User|null $autorizador
 * @property-read Participacion $participacion
 */
class Movimiento extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'fidelidad_movimientos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tipo' => TipoMovimiento::class, 'cantidad' => 'integer', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function autorizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizado_por');
    }

    /** @return BelongsTo<Participacion, $this> */
    public function participacion(): BelongsTo
    {
        return $this->belongsTo(Participacion::class, 'participacion_id');
    }
}
