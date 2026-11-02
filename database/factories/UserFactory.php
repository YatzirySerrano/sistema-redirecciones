<?php

namespace Database\Factories;

use App\Enums\EstadoCuenta;
use App\Enums\RolUsuario;
use App\Models\Negocio;
use App\Models\Rol;
use App\Models\User;
use App\Services\Autorizacion\CatalogoPermisos;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Por defecto crea un administrador activo (útil para pruebas del panel).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'rol' => RolUsuario::Admin,
            'estado' => EstadoCuenta::Activo,
            'negocio_id' => null,
            'debe_cambiar_password' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Rol dinámico al crear: el indicado con conRol() o, si no, el
     * predeterminado del ámbito (Administrador para el personal de DASTI y
     * Cliente para los negocios).
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $usuario) {
            if ($usuario->roles()->exists()) {
                return;
            }

            $clave = $usuario->esCliente() ? CatalogoPermisos::ROL_CLIENTE : CatalogoPermisos::ROL_ADMINISTRADOR;
            $rol = Rol::porClave($clave);

            if ($rol !== null) {
                $usuario->assignRole($rol);
                $usuario->olvidarPermisos();
            }
        });
    }

    /**
     * Asigna un rol concreto (por clave del sistema o por instancia).
     */
    public function conRol(Rol|string $rol): static
    {
        return $this->afterCreating(function (User $usuario) use ($rol) {
            $instancia = $rol instanceof Rol ? $rol : Rol::porClave($rol);
            $usuario->syncRoles([$instancia]);
            $usuario->olvidarPermisos();
        });
    }

    public function superadministrador(): static
    {
        return $this->administrador()->conRol(CatalogoPermisos::ROL_SUPERADMINISTRADOR);
    }

    public function administrador(): static
    {
        return $this->state(fn () => ['rol' => RolUsuario::Admin, 'negocio_id' => null]);
    }

    public function cliente(?Negocio $negocio = null): static
    {
        return $this->state(fn () => [
            'rol' => RolUsuario::Cliente,
            'negocio_id' => $negocio !== null ? $negocio->id : Negocio::factory(),
        ]);
    }

    public function suspendido(): static
    {
        return $this->state(fn () => ['estado' => EstadoCuenta::Suspendido, 'suspendido_en' => now()]);
    }

    public function conContrasenaTemporal(): static
    {
        return $this->state(fn () => ['debe_cambiar_password' => true]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
