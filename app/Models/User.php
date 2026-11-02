<?php

namespace App\Models;

use App\Enums\EstadoCuenta;
use App\Enums\RolUsuario;
use App\Notifications\RestablecerContrasena;
use App\Services\Autorizacion\CatalogoPermisos;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Rol, estado, negocio y bandera de cambio de contraseña no son asignables
 * masivamente: solo los modifican las acciones administrativas auditadas.
 *
 * Autorización en dos dimensiones:
 * - `rol` (RolUsuario) es el ÁMBITO de la cuenta: personal de DASTI
 *   (todos los negocios) o cliente (solo su negocio). Define el alcance.
 * - El rol de spatie/laravel-permission (uno por usuario) define QUÉ
 *   acciones puede realizar dentro de ese alcance (tienePermiso).
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
 * @property CarbonImmutable|null $invitacion_enviada_en
 * @property CarbonImmutable|null $invitacion_fallida_en
 * @property CarbonImmutable|null $invitacion_aceptada_en
 * @property CarbonImmutable|null $ultimo_acceso_en
 * @property CarbonImmutable|null $suspendido_en
 * @property array<string, mixed>|null $preferencias
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Negocio|null $negocio
 * @property-read Collection<int, Rol> $roles
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    protected string $guard_name = 'web';

    /**
     * Permisos efectivos resueltos durante la petición.
     *
     * @var list<string>|null
     */
    private ?array $permisosResueltos = null;

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
            'invitacion_enviada_en' => 'datetime',
            'invitacion_fallida_en' => 'datetime',
            'invitacion_aceptada_en' => 'datetime',
            'ultimo_acceso_en' => 'datetime',
            'suspendido_en' => 'datetime',
            'preferencias' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Negocio, $this>
     */
    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class);
    }

    /**
     * Estado de la invitación de acceso, o null si el usuario se creó con
     * contraseña temporal.
     */
    public function estadoInvitacion(): ?string
    {
        return match (true) {
            $this->invitacion_aceptada_en !== null => 'aceptada',
            $this->invitacion_fallida_en !== null => 'fallida',
            $this->invitacion_enviada_en !== null => 'pendiente',
            default => null,
        };
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
     * Ámbito de la cuenta para el catálogo de permisos.
     */
    public function ambito(): string
    {
        return $this->esAdministrador() ? CatalogoPermisos::AMBITO_PLATAFORMA : CatalogoPermisos::AMBITO_NEGOCIO;
    }

    /**
     * Rol asignado (cada usuario tiene uno).
     */
    public function rolAsignado(): ?Rol
    {
        $this->loadMissing('roles');

        /** @var Rol|null */
        return $this->roles->first();
    }

    public function esSuperadministrador(): bool
    {
        return $this->esAdministrador() && $this->rolAsignado()?->esSuperadministrador() === true;
    }

    /**
     * Permisos efectivos: los del rol que existen en el catálogo y admite el
     * ámbito de la cuenta. Un rol de plataforma asignado por error a un
     * cliente no le otorga permisos de plataforma.
     *
     * @return list<string>
     */
    public function permisosEfectivos(): array
    {
        if ($this->permisosResueltos !== null) {
            return $this->permisosResueltos;
        }

        $this->loadMissing('roles.permissions');
        $rol = $this->rolAsignado();

        if ($rol === null || $rol->ambito !== $this->ambito()) {
            return $this->permisosResueltos = [];
        }

        return $this->permisosResueltos = $rol->nombresPermisos();
    }

    public function tienePermiso(string $permiso): bool
    {
        return in_array($permiso, $this->permisosEfectivos(), true);
    }

    /**
     * Descarta lo resuelto tras cambiar el rol o sus permisos.
     */
    public function olvidarPermisos(): void
    {
        $this->permisosResueltos = null;
        $this->unsetRelation('roles');
    }

    /**
     * Enlace de recuperación de contraseña (en cola, en español). Las cuentas
     * suspendidas no lo reciben; la respuesta de Fortify es la misma para no
     * revelar si la cuenta existe.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->loadMissing('negocio');

        if (! $this->puedeOperar()) {
            return;
        }

        $this->notify(new RestablecerContrasena($token));
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
