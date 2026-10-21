<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Entrada de la bitácora de auditoría.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $negocio_id
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property string $accion
 * @property string $descripcion
 * @property array<string, mixed>|null $valores_anteriores
 * @property array<string, mixed>|null $valores_nuevos
 * @property string|null $ip
 * @property CarbonImmutable $created_at
 * @property-read User|null $usuario
 * @property-read Negocio|null $negocio
 */
class RegistroAuditoria extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'registros_auditoria';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valores_anteriores' => 'array',
            'valores_nuevos' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Negocio, $this>
     */
    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
