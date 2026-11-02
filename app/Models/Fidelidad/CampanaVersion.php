<?php

namespace App\Models\Fidelidad;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reglas de una campaña en una versión concreta (inmutable).
 *
 * @property int $id
 * @property int $campana_id
 * @property int $version
 * @property string|null $descripcion
 * @property int $meta_visitas
 * @property string $recompensa
 * @property int|null $vigencia_visitas_dias
 * @property int|null $vigencia_recompensa_dias
 * @property CarbonImmutable $inicia_en
 * @property CarbonImmutable|null $termina_en
 * @property int|null $limite_participantes
 * @property int $max_visitas_por_dia
 * @property int $intervalo_minimo_minutos
 * @property bool $repetir_ciclo
 * @property int|null $creado_por
 * @property CarbonImmutable $created_at
 * @property-read Campana $campana
 */
class CampanaVersion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'fidelidad_campana_versiones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'inicia_en' => 'date',
            'termina_en' => 'date',
            'repetir_ciclo' => 'boolean',
            'meta_visitas' => 'integer',
            'max_visitas_por_dia' => 'integer',
            'intervalo_minimo_minutos' => 'integer',
        ];
    }

    /** @return BelongsTo<Campana, $this> */
    public function campana(): BelongsTo
    {
        return $this->belongsTo(Campana::class, 'campana_id');
    }

    public function vigenteEn(CarbonImmutable $momento): bool
    {
        return $this->inicia_en->startOfDay() <= $momento
            && ($this->termina_en === null || $this->termina_en->endOfDay() >= $momento);
    }

    /**
     * @return array<string, mixed>
     */
    public function reglas(): array
    {
        return $this->only([
            'descripcion', 'meta_visitas', 'recompensa', 'vigencia_visitas_dias', 'vigencia_recompensa_dias',
            'limite_participantes', 'max_visitas_por_dia', 'intervalo_minimo_minutos', 'repetir_ciclo',
        ]) + [
            'inicia_en' => $this->inicia_en->toDateString(),
            'termina_en' => $this->termina_en?->toDateString(),
        ];
    }
}
