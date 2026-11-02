<?php

namespace App\Models\Fidelidad;

use App\Enums\EstadoRecompensa;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Recompensa generada al completar un ciclo. Alcanzar la meta no la
 * entrega: el canje lo autoriza el negocio y es atómico.
 *
 * @property int $id
 * @property int $negocio_id
 * @property int $participacion_id
 * @property int $consumidor_id
 * @property int $ciclo
 * @property string $descripcion
 * @property EstadoRecompensa $estado
 * @property CarbonImmutable|null $vence_en
 * @property string|null $canje_uuid
 * @property CarbonImmutable|null $canjeada_en
 * @property int|null $canjeada_por
 * @property string|null $motivo
 * @property CarbonImmutable|null $created_at
 * @property-read Participacion $participacion
 * @property-read Consumidor $consumidor
 * @property-read User|null $canjeador
 */
class Recompensa extends Model
{
    protected $table = 'fidelidad_recompensas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['estado' => EstadoRecompensa::class, 'vence_en' => 'datetime', 'canjeada_en' => 'datetime'];
    }

    /** @return BelongsTo<Participacion, $this> */
    public function participacion(): BelongsTo
    {
        return $this->belongsTo(Participacion::class, 'participacion_id');
    }

    /** @return BelongsTo<Consumidor, $this> */
    public function consumidor(): BelongsTo
    {
        return $this->belongsTo(Consumidor::class, 'consumidor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function canjeador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'canjeada_por');
    }

    public function estaDisponible(): bool
    {
        return $this->estado === EstadoRecompensa::Disponible && ($this->vence_en === null || $this->vence_en->isFuture());
    }
}
