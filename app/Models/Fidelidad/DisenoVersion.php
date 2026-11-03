<?php

namespace App\Models\Fidelidad;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Copia de un diseño en un momento importante (publicación o respaldo antes
 * de restaurar). Solo se agrega; se conservan las más recientes.
 *
 * @property int $id
 * @property int $diseno_id
 * @property int $negocio_id
 * @property string $motivo
 * @property array<string, mixed> $documento
 * @property int|null $creado_por
 * @property CarbonImmutable $created_at
 * @property-read Diseno $diseno
 * @property-read User|null $autor
 */
class DisenoVersion extends Model
{
    public const UPDATED_AT = null;

    public const PUBLICACION = 'publicacion';

    public const RESPALDO = 'respaldo';

    protected $table = 'fidelidad_diseno_versiones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['documento' => 'array', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<Diseno, $this> */
    public function diseno(): BelongsTo
    {
        return $this->belongsTo(Diseno::class, 'diseno_id');
    }

    /** @return BelongsTo<User, $this> */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }
}
