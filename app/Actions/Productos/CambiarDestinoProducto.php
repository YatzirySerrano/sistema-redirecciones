<?php

namespace App\Actions\Productos;

use App\Enums\OrigenCambio;
use App\Models\CambioDestino;
use App\Models\Producto;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use App\Services\Redirecciones\ValidadorUrlDestino;
use Illuminate\Support\Facades\DB;

/**
 * Cambia la URL de destino de un producto. El enlace permanente (código) no
 * se modifica: el QR impreso y la tarjeta NFC siguen funcionando.
 *
 * La autorización se resuelve antes (ProductoPolicy); aquí se garantiza la
 * consistencia: bloqueo de fila, historial y auditoría en una transacción.
 */
class CambiarDestinoProducto
{
    public function __construct(
        private readonly ValidadorUrlDestino $validador,
        private readonly RegistradorAuditoria $auditoria,
    ) {}

    /**
     * @return bool true si el destino cambió.
     */
    public function ejecutar(Producto $producto, ?string $url, User $actor): bool
    {
        $url = $url === null || trim($url) === '' ? null : $this->validador->normalizar($url);

        return DB::transaction(function () use ($producto, $url, $actor) {
            /** @var Producto $bloqueado */
            $bloqueado = Producto::query()->whereKey($producto->id)->lockForUpdate()->firstOrFail();
            $anterior = $bloqueado->url_destino;

            if ($anterior === $url) {
                return false;
            }

            $bloqueado->forceFill([
                'url_destino' => $url,
                'destino_actualizado_en' => now(),
            ])->save();

            CambioDestino::query()->create([
                'producto_id' => $bloqueado->id,
                'negocio_id' => $bloqueado->negocio_id,
                'user_id' => $actor->id,
                'origen' => $actor->esAdministrador() ? OrigenCambio::Admin : OrigenCambio::Cliente,
                'url_anterior' => $anterior,
                'url_nueva' => $url,
            ]);

            $this->auditoria->registrar(
                accion: 'producto.destino_cambiado',
                descripcion: "Cambio de destino de «{$bloqueado->nombre}»",
                recurso: $bloqueado,
                negocio: $bloqueado->negocio_id,
                anteriores: ['url_destino' => $anterior],
                nuevos: ['url_destino' => $url],
                actor: $actor,
            );

            $producto->setRawAttributes($bloqueado->getAttributes(), true);

            return true;
        });
    }
}
