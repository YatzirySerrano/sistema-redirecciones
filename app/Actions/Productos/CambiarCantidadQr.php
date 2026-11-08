<?php

namespace App\Actions\Productos;

use App\Enums\EstadoProducto;
use App\Enums\MedioEnlace;
use App\Enums\ModalidadDestino;
use App\Enums\TipoProducto;
use App\Models\Producto;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Amplía un producto QR o QR + NFC a dos QR impresos, o lo reduce a uno.
 *
 * Ampliar (1 → 2): dos QR implican destinos independientes.
 * - QR: el enlace actual pasa a ser «QR 1» (mismo código: el QR ya impreso
 *   sigue funcionando) y se agrega «QR 2».
 * - Combinado independiente: el reverso pasa a ser «QR 1» y se agrega «QR 2».
 * - Combinado compartido: el enlace actual queda para el NFC (no hay que
 *   regrabar el chip) y se crean «QR 1» (con el mismo destino y diseño, para
 *   la nueva impresión) y «QR 2».
 * Si el producto tuvo antes un QR 2 archivado, se reactiva ese mismo enlace
 * (mismo código, destino e historial) en lugar de crear otro.
 *
 * Reducir (2 → 1): el QR 2 nunca se borra (conserva historial, estadísticas
 * y auditoría): queda suspendido y archivado, deja de redirigir («no
 * disponible») y desaparece de las fichas. El QR 1 recupera su etiqueta
 * («QR» o «QR (reverso)»).
 */
class CambiarCantidadQr
{
    public function __construct(
        private readonly CrearProducto $crearProducto,
        private readonly RegistradorAuditoria $auditoria,
    ) {}

    public function ejecutar(Producto $producto, int $cantidad, User $actor, ?string $motivo = null, ?string $urlDestinoQr2 = null): Producto
    {
        if (! in_array($cantidad, [1, 2], true)) {
            throw ValidationException::withMessages(['cantidad_qr' => 'El producto puede llevar 1 o 2 QR.']);
        }

        $principalId = $producto->producto_principal_id ?? $producto->id;

        return DB::transaction(function () use ($principalId, $cantidad, $actor, $motivo, $urlDestinoQr2) {
            /** @var Producto $principal */
            $principal = Producto::query()->with('negocio')->whereKey($principalId)->lockForUpdate()->firstOrFail();

            if (! $principal->admiteVariosQr()) {
                throw ValidationException::withMessages(['cantidad_qr' => 'Los productos NFC no llevan QR impreso.']);
            }

            $anterior = $principal->cantidadQr();

            if ($anterior === $cantidad) {
                return $principal;
            }

            $enlaces = Producto::query()->where('producto_principal_id', $principal->id)->lockForUpdate()->orderBy('id')->get();
            $qr2 = $cantidad === 2
                ? $this->ampliar($principal, array_values($enlaces->all()), $actor, $urlDestinoQr2)
                : $this->reducir($principal, array_values($enlaces->all()));

            $principal->refresh();

            $this->auditoria->registrar(
                accion: $cantidad === 2 ? 'producto.qr_ampliado' : 'producto.qr_reducido',
                descripcion: ($cantidad === 2 ? 'Ampliación a 2 QR' : 'Reducción a 1 QR')." de «{$principal->nombre}»",
                recurso: $principal,
                negocio: $principal->negocio_id,
                anteriores: ['cantidad_qr' => $anterior],
                nuevos: array_filter([
                    'cantidad_qr' => $cantidad,
                    'codigo_qr2' => $qr2->codigo,
                    'qr2' => $cantidad === 2 ? 'activo' : 'archivado',
                    'motivo' => $motivo !== null && trim($motivo) !== '' ? trim($motivo) : null,
                ], fn ($v) => $v !== null),
                actor: $actor,
            );

            return $principal;
        });
    }

    /**
     * @param  list<Producto>  $enlaces
     */
    private function ampliar(Producto $principal, array $enlaces, User $actor, ?string $urlDestinoQr2): Producto
    {
        $vigentes = array_values(array_filter($enlaces, fn (Producto $e) => ! $e->estaArchivado()));
        $archivadoQr2 = collect($enlaces)->first(fn (Producto $e) => $e->estaArchivado() && $e->numero_qr === 2);

        if ($principal->tipo === TipoProducto::Qr) {
            $principal->forceFill([
                'modalidad_destino' => ModalidadDestino::Independiente,
                'medio' => MedioEnlace::Qr,
                'numero_qr' => 1,
            ]);
        } elseif ($principal->tieneDestinosIndependientes() && $vigentes !== []) {
            // Combinado independiente: el reverso existente es el QR 1.
            $vigentes[0]->forceFill(['numero_qr' => 1])->save();
        } else {
            // Combinado compartido: el enlace actual queda para el NFC.
            $principal->forceFill([
                'modalidad_destino' => ModalidadDestino::Independiente,
                'medio' => MedioEnlace::Nfc,
                'numero_qr' => null,
            ]);
            $this->crearProducto->crearEnlaceQr($principal, null, $actor, $principal->url_destino, 1, $principal->opciones_qr);
        }

        $principal->forceFill(['cantidad_qr' => 2])->save();

        if ($archivadoQr2 instanceof Producto) {
            $archivadoQr2->forceFill([
                'archivado_en' => null,
                'estado' => $principal->estado,
                'suspendido_en' => $principal->estado === EstadoProducto::Suspendido ? $principal->suspendido_en : null,
                'nombre' => $principal->nombre,
            ])->save();

            return $archivadoQr2;
        }

        return $this->crearProducto->crearEnlaceQr($principal, null, $actor, $urlDestinoQr2, 2);
    }

    /**
     * @param  list<Producto>  $enlaces
     */
    private function reducir(Producto $principal, array $enlaces): Producto
    {
        $vigentes = collect($enlaces)->reject(fn (Producto $e) => $e->estaArchivado());
        $qr2 = $vigentes->first(fn (Producto $e) => $e->numero_qr === 2);

        if (! $qr2 instanceof Producto) {
            throw ValidationException::withMessages(['cantidad_qr' => 'El producto no tiene un segundo QR vigente.']);
        }

        $qr2->forceFill([
            'estado' => EstadoProducto::Suspendido,
            'suspendido_en' => $qr2->suspendido_en ?? now(),
            'archivado_en' => now(),
        ])->save();

        $vigentes->reject(fn (Producto $e) => $e->is($qr2))
            ->each(fn (Producto $e) => $e->forceFill(['numero_qr' => null])->save());

        if ($principal->tipo === TipoProducto::Qr) {
            // Un solo QR: vuelve a ser un enlace simple.
            $principal->forceFill([
                'modalidad_destino' => ModalidadDestino::Compartido,
                'medio' => null,
            ]);
        }

        $principal->forceFill(['cantidad_qr' => 1, 'numero_qr' => null])->save();

        return $qr2;
    }
}
