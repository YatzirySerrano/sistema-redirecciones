<?php

namespace App\Models\Fidelidad;

use App\Models\Negocio;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Apariencia de la tarjeta digital de Fidelidad: general del negocio
 * (`campana_id` nulo) o variante de una campaña. Solo presentación: nunca
 * define metas, visitas ni recompensas (esas vienen de la campaña).
 *
 * @property int $id
 * @property int $negocio_id
 * @property int|null $campana_id
 * @property int $campana_clave
 * @property array<string, mixed> $borrador
 * @property array<string, mixed>|null $publicado
 * @property int $version
 * @property CarbonImmutable|null $publicado_en
 * @property int|null $publicado_por
 * @property int|null $actualizado_por
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Negocio $negocio
 * @property-read Campana|null $campana
 */
class Diseno extends Model
{
    protected $table = 'fidelidad_disenos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'borrador' => 'array',
            'publicado' => 'array',
            'version' => 'integer',
            'campana_clave' => 'integer',
            'publicado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Negocio, $this> */
    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class);
    }

    /** @return BelongsTo<Campana, $this> */
    public function campana(): BelongsTo
    {
        return $this->belongsTo(Campana::class, 'campana_id');
    }

    /** @return HasMany<DisenoVersion, $this> */
    public function versiones(): HasMany
    {
        return $this->hasMany(DisenoVersion::class, 'diseno_id');
    }

    public function tieneCambiosSinPublicar(): bool
    {
        return $this->publicado === null || $this->publicado != $this->borrador;
    }
}
