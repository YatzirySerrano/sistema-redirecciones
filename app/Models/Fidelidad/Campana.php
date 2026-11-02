<?php

namespace App\Models\Fidelidad;

use App\Enums\EstadoCampana;
use App\Models\Negocio;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Programa de visitas de un negocio. Sus reglas viven en versiones
 * inmutables (CampanaVersion); las participaciones conservan la suya.
 *
 * @property int $id
 * @property int $negocio_id
 * @property string $nombre
 * @property EstadoCampana $estado
 * @property int $version_actual
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Negocio $negocio
 * @property-read CampanaVersion|null $vigente
 */
class Campana extends Model
{
    protected $table = 'fidelidad_campanas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['estado' => EstadoCampana::class];
    }

    /** @return BelongsTo<Negocio, $this> */
    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class);
    }

    /** @return HasMany<CampanaVersion, $this> */
    public function versiones(): HasMany
    {
        return $this->hasMany(CampanaVersion::class, 'campana_id');
    }

    /** @return HasOne<CampanaVersion, $this> */
    public function vigente(): HasOne
    {
        // Las versiones solo crecen: la vigente es siempre la más alta.
        return $this->hasOne(CampanaVersion::class, 'campana_id')->latestOfMany('version');
    }

    /** @return HasMany<Participacion, $this> */
    public function participaciones(): HasMany
    {
        return $this->hasMany(Participacion::class, 'campana_id');
    }
}
