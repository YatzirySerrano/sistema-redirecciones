<?php

namespace App\Models\Fidelidad;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Enlace temporal de un solo uso (verificación de correo o renovación del
 * enlace de consulta). Solo se guarda el hash del token.
 *
 * @property int $id
 * @property int $consumidor_id
 * @property string $token_hash
 * @property string $proposito
 * @property CarbonImmutable $expira_en
 * @property CarbonImmutable|null $usado_en
 * @property-read Consumidor $consumidor
 */
class Recuperacion extends Model
{
    public const UPDATED_AT = null;

    public const VERIFICAR = 'verificar';

    public const RENOVAR = 'renovar';

    protected $table = 'fidelidad_recuperaciones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expira_en' => 'datetime', 'usado_en' => 'datetime'];
    }

    /** @return BelongsTo<Consumidor, $this> */
    public function consumidor(): BelongsTo
    {
        return $this->belongsTo(Consumidor::class, 'consumidor_id');
    }
}
