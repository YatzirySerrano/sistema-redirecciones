<?php

namespace App\Models\Fidelidad;

use App\Models\Negocio;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

/**
 * Cuenta de fidelidad de una persona en UN negocio (no es usuario del
 * panel). Sus visitas pertenecen a esta cuenta, no a la tarjeta física.
 *
 * @property int $id
 * @property int $negocio_id
 * @property string $nombre
 * @property string|null $alias
 * @property string|null $telefono
 * @property string|null $email
 * @property CarbonImmutable|null $email_verificado_en
 * @property CarbonImmutable $consentimiento_en
 * @property string $consentimiento_version
 * @property string $estado
 * @property string|null $token_acceso_hash
 * @property CarbonImmutable|null $token_acceso_emitido_en
 * @property int|null $fusionado_en_id
 * @property int|null $registrado_por
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Negocio $negocio
 */
class Consumidor extends Model
{
    use Notifiable;

    public const ACTIVO = 'activo';

    public const BLOQUEADO = 'bloqueado';

    public const FUSIONADO = 'fusionado';

    protected $table = 'fidelidad_consumidores';

    protected $guarded = ['id'];

    protected $hidden = ['token_acceso_hash'];

    protected function casts(): array
    {
        return [
            'email_verificado_en' => 'datetime',
            'consentimiento_en' => 'datetime',
            'token_acceso_emitido_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Negocio, $this> */
    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class);
    }

    /** @return HasMany<Credencial, $this> */
    public function credenciales(): HasMany
    {
        return $this->hasMany(Credencial::class, 'consumidor_id');
    }

    /** @return HasMany<Participacion, $this> */
    public function participaciones(): HasMany
    {
        return $this->hasMany(Participacion::class, 'consumidor_id');
    }

    /** @return HasMany<Recompensa, $this> */
    public function recompensas(): HasMany
    {
        return $this->hasMany(Recompensa::class, 'consumidor_id');
    }

    public function estaActivo(): bool
    {
        return $this->estado === self::ACTIVO;
    }

    public function nombreVisible(): string
    {
        return $this->alias ?: explode(' ', trim($this->nombre))[0];
    }

    public function routeNotificationForMail(): ?string
    {
        return $this->email_verificado_en !== null || $this->email !== null ? $this->email : null;
    }

    /**
     * @param  Builder<Consumidor>  $query
     * @return Builder<Consumidor>
     */
    public function scopeDeNegocio(Builder $query, int $negocioId): Builder
    {
        return $query->where('negocio_id', $negocioId);
    }
}
