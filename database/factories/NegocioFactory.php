<?php

namespace Database\Factories;

use App\Enums\EstadoCuenta;
use App\Models\Negocio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Negocio>
 */
class NegocioFactory extends Factory
{
    protected $model = Negocio::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre_comercial' => fake()->unique()->company(),
            'responsable' => fake()->name(),
            'email_contacto' => fake()->unique()->safeEmail(),
            'telefono' => fake()->numerify('55 #### ####'),
            'notas_internas' => null,
            'estado' => EstadoCuenta::Activo,
        ];
    }

    public function suspendido(): static
    {
        return $this->state(fn () => ['estado' => EstadoCuenta::Suspendido, 'suspendido_en' => now()]);
    }
}
