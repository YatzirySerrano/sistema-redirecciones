<?php

namespace App\Actions\Productos;

use App\Enums\EstadoPreparacion;
use App\Enums\EstadoProducto;
use App\Enums\MedioEnlace;
use App\Enums\ModalidadDestino;
use App\Enums\OrigenCambio;
use App\Enums\TipoProducto;
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
     * Un combinado con `modalidad_destino` = independiente crea, por cada
     * unidad, la fila principal (frente NFC, `url_destino`) y su enlace
     * secundario (reverso QR, `url_destino_qr`), cada uno con su código.
     *
     * `cantidad_qr` = 2 (QR y combinados) agrega un segundo QR impreso con su
     * propio enlace (`url_destino_qr2`). Dos QR implican destinos
     * independientes:
     * - QR con 2 QR: principal = QR 1, secundario = QR 2.
     * - Combinado con 2 QR: principal = NFC, secundarios = QR 1 y QR 2.
     *
     * @param  array{nombre: string, tipo: string, modalidad_destino?: string|null, cantidad_qr?: int|string|null, url_destino?: string|null, url_destino_qr?: string|null, url_destino_qr2?: string|null, estado_preparacion?: string|null, fecha_entrega?: string|null, referencia_comercial?: string|null, notas_internas?: string|null}  $datos
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
        $tipo = TipoProducto::from((string) $datos['tipo']);
        $cantidadQr = $tipo === TipoProducto::Nfc ? null : ((int) ($datos['cantidad_qr'] ?? 1) === 2 ? 2 : 1);
        $dosQr = $cantidadQr === 2;
        $combinadoIndependiente = $tipo === TipoProducto::Combinado
            && ($dosQr || ($datos['modalidad_destino'] ?? null) === ModalidadDestino::Independiente->value);
        $independiente = $combinadoIndependiente || $dosQr;

        $producto = $this->crearEnlace($negocio, $datos, $actor, $datos['url_destino'] ?? null, [
            'modalidad_destino' => $independiente ? ModalidadDestino::Independiente : ModalidadDestino::Compartido,
            'medio' => match (true) {
                $combinadoIndependiente => MedioEnlace::Nfc,
                $dosQr => MedioEnlace::Qr,
                default => null,
            },
            'cantidad_qr' => $cantidadQr,
            'numero_qr' => $dosQr && ! $combinadoIndependiente ? 1 : null,
        ]);

        $secundario = null;
        $segundoQr = null;

        if ($combinadoIndependiente) {
            $secundario = $this->crearEnlaceQr($producto, $datos, $actor, $datos['url_destino_qr'] ?? null, $dosQr ? 1 : null);
        }

        if ($dosQr) {
            $segundoQr = $this->crearEnlaceQr($producto, $datos, $actor, $datos['url_destino_qr2'] ?? null, 2);
        }

        $this->auditoria->registrar(
            accion: 'producto.creado',
            descripcion: "Alta de «{$producto->nombre}» para «{$negocio->nombre_comercial}»",
            recurso: $producto,
            negocio: $negocio,
            nuevos: array_filter([
                'nombre' => $producto->nombre,
                'tipo' => $producto->tipo,
                'modalidad_destino' => $independiente ? ModalidadDestino::Independiente : null,
                'cantidad_qr' => $dosQr ? 2 : null,
                'codigo' => $producto->codigo,
                'url_destino' => $producto->url_destino,
                'codigo_qr_reverso' => $secundario?->codigo,
                'url_destino_qr' => $secundario?->url_destino,
                'codigo_qr2' => $segundoQr?->codigo,
                'url_destino_qr2' => $segundoQr?->url_destino,
            ], fn ($v) => $v !== null),
            actor: $actor,
        );

        return $producto;
    }

    /**
     * Enlace secundario QR del producto físico (reverso, QR 1 o QR 2). Copia
     * los datos administrativos del principal.
     *
     * @param  array<string, mixed>|null  $datos
     * @param  array<string, mixed>|null  $opcionesQr
     */
    public function crearEnlaceQr(Producto $principal, ?array $datos, User $actor, mixed $destino, ?int $numeroQr, ?array $opcionesQr = null): Producto
    {
        $datos ??= [
            'nombre' => $principal->nombre,
            'estado_preparacion' => $principal->estado_preparacion->value,
            'fecha_entrega' => $principal->fecha_entrega?->toDateString(),
            'referencia_comercial' => $principal->referencia_comercial,
            'notas_internas' => $principal->notas_internas,
        ];

        $principal->loadMissing('negocio');

        return $this->crearEnlace($principal->negocio, [...$datos, 'tipo' => TipoProducto::Qr->value], $actor, $destino, [
            'producto_principal_id' => $principal->id,
            'modalidad_destino' => ModalidadDestino::Independiente,
            'medio' => MedioEnlace::Qr,
            'numero_qr' => $numeroQr,
            'cantidad_qr' => null,
            'estado' => $principal->estado,
            'suspendido_en' => $principal->suspendido_en,
            'opciones_qr' => $opcionesQr,
        ]);
    }

    /**
     * Crea una fila de `productos` con su código único y, si tiene destino,
     * el primer registro de su historial.
     *
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $extra
     */
    private function crearEnlace(Negocio $negocio, array $datos, User $actor, mixed $destino, array $extra): Producto
    {
        $url = is_string($destino) && $destino !== '' ? $this->validador->normalizar($destino) : null;

        $producto = new Producto([
            'nombre' => $datos['nombre'],
            'tipo' => $datos['tipo'],
            'estado_preparacion' => $datos['estado_preparacion'] ?? EstadoPreparacion::Pendiente->value,
            'fecha_entrega' => $datos['fecha_entrega'] ?? null,
            'referencia_comercial' => $datos['referencia_comercial'] ?? null,
            'notas_internas' => $datos['notas_internas'] ?? null,
        ]);
        $producto->forceFill([
            'estado' => EstadoProducto::Activo,
            ...$extra,
            'negocio_id' => $negocio->id,
            'url_destino' => $url,
            'destino_actualizado_en' => $url !== null ? now() : null,
        ]);

        $this->guardarConCodigoUnico($producto);

        if ($url !== null) {
            CambioDestino::query()->create([
                'producto_id' => $producto->id,
                'negocio_id' => $negocio->id,
                'user_id' => $actor->id,
                'origen' => $actor->esAdministrador() ? OrigenCambio::Admin : OrigenCambio::Cliente,
                'url_anterior' => null,
                'url_nueva' => $url,
            ]);
        }

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
