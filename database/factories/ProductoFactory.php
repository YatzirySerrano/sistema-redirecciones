<?php

namespace Database\Factories;

use App\Enums\EstadoPreparacion;
use App\Enums\EstadoProducto;
use App\Enums\TipoProducto;
use App\Models\Negocio;
use App\Models\Producto;
use App\Services\Redirecciones\GeneradorCodigo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    protected $model = Producto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'negocio_id' => Negocio::factory(),
            'codigo' => fn () => app(GeneradorCodigo::class)->generar(),
            'nombre' => 'Tarjeta '.fake()->word(),
            'tipo' => TipoProducto::Nfc,
            'url_destino' => 'https://www.example.org/'.fake()->slug(2),
            'estado' => EstadoProducto::Activo,
            'estado_preparacion' => EstadoPreparacion::Entregado,
            'destino_actualizado_en' => now(),
        ];
    }

    public function qr(): static
    {
        return $this->state(fn () => ['tipo' => TipoProducto::Qr]);
    }

    public function suspendido(): static
    {
        return $this->state(fn () => ['estado' => EstadoProducto::Suspendido, 'suspendido_en' => now()]);
    }

    public function sinDestino(): static
    {
        return $this->state(fn () => ['url_destino' => null, 'destino_actualizado_en' => null]);
    }
}
