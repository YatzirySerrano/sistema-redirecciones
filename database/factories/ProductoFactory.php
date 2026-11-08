<?php

namespace Database\Factories;

use App\Enums\EstadoPreparacion;
use App\Enums\EstadoProducto;
use App\Enums\MedioEnlace;
use App\Enums\ModalidadDestino;
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
        return $this->state(fn () => ['tipo' => TipoProducto::Qr, 'cantidad_qr' => 1]);
    }

    /**
     * Producto QR con dos QR impresos: la fila principal es el «QR 1» y se
     * crea su enlace secundario «QR 2», cada uno con su código y destino.
     */
    public function dosQr(): static
    {
        return $this->state(fn () => [
            'tipo' => TipoProducto::Qr,
            'cantidad_qr' => 2,
            'modalidad_destino' => ModalidadDestino::Independiente,
            'medio' => MedioEnlace::Qr,
            'numero_qr' => 1,
        ])->afterCreating(function (Producto $producto) {
            Producto::factory()->create([
                'negocio_id' => $producto->negocio_id,
                'producto_principal_id' => $producto->id,
                'nombre' => $producto->nombre,
                'tipo' => TipoProducto::Qr,
                'modalidad_destino' => ModalidadDestino::Independiente,
                'medio' => MedioEnlace::Qr,
                'numero_qr' => 2,
                'estado' => $producto->estado,
                'suspendido_en' => $producto->suspendido_en,
            ]);
        });
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
