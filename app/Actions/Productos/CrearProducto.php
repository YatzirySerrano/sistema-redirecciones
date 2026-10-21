<?php

namespace App\Actions\Productos;

use App\Enums\EstadoPreparacion;
use App\Enums\EstadoProducto;
use App\Enums\OrigenCambio;
use App\Models\CambioDestino;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use App\Services\Redirecciones\GeneradorCodigo;
use App\Services\Redirecciones\ValidadorUrlDestino;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Registra uno o varios productos físicos para un negocio, cada uno con su
 * propio enlace permanente.
 *
 * Cada unidad recibe un código distinto de GeneradorCodigo. La unicidad la
 * garantiza el índice UNIQUE de productos.codigo: si dos altas simultáneas
 * llegaran a generar el mismo código, el INSERT perdedor se reintenta con un
 * código nuevo (dentro de un savepoint) en lugar de fallar.
 */
class CrearProducto
{
    private const INTENTOS_CODIGO = 5;

    public function __construct(
        private readonly GeneradorCodigo $generadorCodigo,
        private readonly ValidadorUrlDestino $validador,
        private readonly RegistradorAuditoria $auditoria,
    ) {}

    /**
     * @param  array{nombre: string, tipo: string, url_destino?: string|null, estado_preparacion?: string|null, fecha_entrega?: string|null, referencia_comercial?: string|null, notas_internas?: string|null}  $datos
     * @return list<Producto>
     */
    public function ejecutar(Negocio $negocio, array $datos, User $actor, int $cantidad = 1): array
    {
        $cantidad = max(1, min(50, $cantidad));

        return DB::transaction(function () use ($negocio, $datos, $actor, $cantidad) {
            $productos = [];

            for ($i = 1; $i <= $cantidad; $i++) {
                $productos[] = $this->crearUno(
                    $negocio,
                    [...$datos, 'nombre' => $cantidad > 1 ? "{$datos['nombre']} {$i}" : $datos['nombre']],
                    $actor,
                );
            }

            return $productos;
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function crearUno(Negocio $negocio, array $datos, User $actor): Producto
    {
        $url = isset($datos['url_destino']) && $datos['url_destino'] !== ''
            ? $this->validador->normalizar((string) $datos['url_destino'])
            : null;

        $producto = new Producto([
            'nombre' => $datos['nombre'],
            'tipo' => $datos['tipo'],
            'estado_preparacion' => $datos['estado_preparacion'] ?? EstadoPreparacion::Pendiente->value,
            'fecha_entrega' => $datos['fecha_entrega'] ?? null,
            'referencia_comercial' => $datos['referencia_comercial'] ?? null,
            'notas_internas' => $datos['notas_internas'] ?? null,
        ]);
        $producto->forceFill([
            'negocio_id' => $negocio->id,
            'estado' => EstadoProducto::Activo,
            'url_destino' => $url,
            'destino_actualizado_en' => $url !== null ? now() : null,
        ]);

        $this->guardarConCodigoUnico($producto);

        if ($url !== null) {
            CambioDestino::query()->create([
                'producto_id' => $producto->id,
                'negocio_id' => $negocio->id,
                'user_id' => $actor->id,
                'origen' => OrigenCambio::Admin,
                'url_anterior' => null,
                'url_nueva' => $url,
            ]);
        }

        $this->auditoria->registrar(
            accion: 'producto.creado',
            descripcion: "Alta de «{$producto->nombre}» para «{$negocio->nombre_comercial}»",
            recurso: $producto,
            negocio: $negocio,
            nuevos: [
                'nombre' => $producto->nombre,
                'tipo' => $producto->tipo,
                'codigo' => $producto->codigo,
                'url_destino' => $url,
            ],
            actor: $actor,
        );

        return $producto;
    }

    private function guardarConCodigoUnico(Producto $producto): void
    {
        for ($intento = 1; ; $intento++) {
            $producto->codigo = $this->generadorCodigo->generar();

            try {
                // Transacción anidada = savepoint: un INSERT rechazado no
                // invalida el resto del lote.
                DB::transaction(fn () => $producto->save());

                return;
            } catch (UniqueConstraintViolationException $e) {
                if ($intento >= self::INTENTOS_CODIGO) {
                    throw $e;
                }
            }
        }
    }
}
