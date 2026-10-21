<?php

namespace App\Models;

use App\Enums\EstadoCuenta;
use App\Enums\RolUsuario;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * Rol, estado, negocio y bandera de cambio de contraseña no son asignables
 * masivamente: solo los modifican las acciones administrativas auditadas.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property RolUsuario $rol
 * @property EstadoCuenta $estado
 * @property int|null $negocio_id
 * @property bool $debe_cambiar_password
 * @property CarbonImmutable|null $ultimo_acceso_en
 * @property CarbonImmutable|null $suspendido_en
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Negocio|null $negocio
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'rol' => RolUsuario::class,
            'estado' => EstadoCuenta::class,
            'debe_cambiar_password' => 'boolean',
            'ultimo_acceso_en' => 'datetime',
            'suspendido_en' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Negocio, $this>
     */
    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class);
    }

    public function esAdministrador(): bool
    {
        return $this->rol === RolUsuario::Admin;
    }

    public function esCliente(): bool
    {
        return $this->rol === RolUsuario::Cliente;
    }

    /**
     * Un usuario puede operar si su cuenta está activa y, en el caso de los
     * clientes, si además pertenece a un negocio activo.
     */
    public function puedeOperar(): bool
    {
        if ($this->estado !== EstadoCuenta::Activo) {
            return false;
        }

        if ($this->esAdministrador()) {
            return true;
        }

        return $this->negocio !== null && $this->negocio->estaActivo();
    }

    public function perteneceANegocio(int $negocioId): bool
    {
        return $this->esCliente() && $this->negocio_id === $negocioId;
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeClientes(Builder $query): Builder
    {
        return $query->where('rol', RolUsuario::Cliente);
    }
}
