<?php

namespace App\Models\Fidelidad;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Inscripción de un consumidor en una campaña, con la versión de reglas
 * vigente al inscribirse. `visitas_ciclo` es el saldo del ciclo actual.
 *
 * @property int $id
 * @property int $negocio_id
 * @property int $consumidor_id
 * @property int $campana_id
 * @property int $campana_version_id
 * @property int $ciclo
 * @property int $visitas_ciclo
 * @property int $visitas_totales
 * @property CarbonImmutable|null $ultima_visita_en
 * @property string $estado
 * @property-read Consumidor $consumidor
 * @property-read Campana $campana
 * @property-read CampanaVersion $version
 */
class Participacion extends Model
{
    public const ACTIVA = 'activa';

    public const COMPLETADA = 'completada';

    public const FUSIONADA = 'fusionada';

    protected $table = 'fidelidad_participaciones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'ultima_visita_en' => 'datetime',
            'ciclo' => 'integer',
            'visitas_ciclo' => 'integer',
            'visitas_totales' => 'integer',
        ];
    }

    /** @return BelongsTo<Consumidor, $this> */
    public function consumidor(): BelongsTo
    {
        return $this->belongsTo(Consumidor::class, 'consumidor_id');
    }

    /** @return BelongsTo<Campana, $this> */
    public function campana(): BelongsTo
    {
        return $this->belongsTo(Campana::class, 'campana_id');
    }

    /** @return BelongsTo<CampanaVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(CampanaVersion::class, 'campana_version_id');
    }

    /** @return HasMany<Movimiento, $this> */
    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class, 'participacion_id');
    }
}
