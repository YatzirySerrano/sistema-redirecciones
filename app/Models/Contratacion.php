<?php

namespace App\Models;

use App\Enums\EstadoContratacion;
use App\Enums\OrigenContratacion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Periodo contratado de un servicio por un negocio (historial: un registro
 * por periodo). Se modifica solo desde App\Services\Servicios\GestorContrataciones.
 *
 * @property int $id
 * @property int $negocio_id
 * @property int $servicio_id
 * @property EstadoContratacion $estado
 * @property OrigenContratacion $origen
 * @property int|null $pago_id
 * @property string $modalidad
 * @property CarbonImmutable|null $inicia_en
 * @property CarbonImmutable|null $vence_en
 * @property string|null $motivo
 * @property CarbonImmutable|null $estado_cambiado_en
 * @property bool $restriccion_manual
 * @property int|null $creado_por
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Negocio $negocio
 * @property-read Servicio $servicio
 * @property-read Pago|null $pago
 */
class Contratacion extends Model
{
    protected $table = 'contrataciones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoContratacion::class,
            'origen' => OrigenContratacion::class,
            'inicia_en' => 'datetime',
            'vence_en' => 'datetime',
            'estado_cambiado_en' => 'datetime',
            'restriccion_manual' => 'boolean',
        ];
    }

    /** @return BelongsTo<Negocio, $this> */
    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class);
    }

    /** @return BelongsTo<Servicio, $this> */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    /** @return BelongsTo<Pago, $this> */
    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    public function estaVencida(): bool
    {
        return $this->vence_en !== null && $this->vence_en->isPast();
    }

    /**
     * Activa y dentro de su vigencia.
     */
    public function otorgaAcceso(): bool
    {
        return $this->estado === EstadoContratacion::Activa && ! $this->estaVencida();
    }

    /**
     * @param  Builder<Contratacion>  $query
     * @return Builder<Contratacion>
     */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('estado', EstadoContratacion::Activa)
            ->where(fn (Builder $q) => $q->whereNull('vence_en')->orWhere('vence_en', '>', now()));
    }
}
