<?php

namespace App\Models;

use App\Services\Autorizacion\CatalogoPermisos;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

/**
 * Rol de la plataforma (spatie/laravel-permission) con los datos propios de
 * DASTI: clave de los roles del sistema, descripción y ámbito.
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property string|null $clave
 * @property string|null $descripcion
 * @property string $ambito
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read int|null $users_count
 * @property-read int|null $permissions_count
 */
class Rol extends Role
{
    public function esSuperadministrador(): bool
    {
        return $this->clave === CatalogoPermisos::ROL_SUPERADMINISTRADOR;
    }

    /**
     * Los roles del sistema no se eliminan ni cambian de nombre.
     */
    public function esDelSistema(): bool
    {
        return $this->clave !== null;
    }

    public function esDeNegocio(): bool
    {
        return $this->ambito === CatalogoPermisos::AMBITO_NEGOCIO;
    }

    /**
     * Nombres de los permisos del rol que existen en el catálogo y admite
     * su ámbito (defensa ante datos manipulados en la base).
     *
     * @return list<string>
     */
    public function nombresPermisos(): array
    {
        if ($this->esSuperadministrador()) {
            return CatalogoPermisos::delAmbito(CatalogoPermisos::AMBITO_PLATAFORMA);
        }

        return array_values(array_filter(
            $this->permissions->pluck('name')->map(fn ($n) => (string) $n)->all(),
            fn (string $p) => CatalogoPermisos::admiteAmbito($p, $this->ambito),
        ));
    }

    /**
     * @param  Builder<Rol>  $query
     * @return Builder<Rol>
     */
    public function scopeDelAmbito(Builder $query, string $ambito): Builder
    {
        return $query->where('ambito', $ambito);
    }

    public static function porClave(string $clave): ?self
    {
        return self::query()->where('clave', $clave)->first();
    }
}
