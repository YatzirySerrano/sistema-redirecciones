<?php

namespace App\Policies;

use App\Models\Producto;
use App\Models\User;
use App\Services\Configuracion\AjustesPlataforma;
use Illuminate\Auth\Access\Response;

/**
 * Autorización sobre productos.
 *
 * Para clientes, cualquier producto ajeno se responde como inexistente (404)
 * en lugar de 403, para no confirmar la existencia de recursos de otros
 * negocios a quien manipula identificadores.
 */
class ProductoPolicy
{
    public function __construct(private readonly AjustesPlataforma $ajustes) {}

    public function ver(User $usuario, Producto $producto): Response
    {
        if ($usuario->esAdministrador()) {
            return Response::allow();
        }

        return $usuario->perteneceANegocio($producto->negocio_id)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function cambiarDestino(User $usuario, Producto $producto): Response
    {
        if ($usuario->esAdministrador()) {
            return Response::allow();
        }

        if (! $usuario->perteneceANegocio($producto->negocio_id)) {
            return Response::denyAsNotFound();
        }

        return $producto->admiteCambiosDelCliente()
            ? Response::allow()
            : Response::deny('Este producto está suspendido. Contacta a DASTI para reactivarlo.');
    }

    public function descargarQr(User $usuario, Producto $producto): Response
    {
        if ($usuario->esAdministrador()) {
            return Response::allow();
        }

        if (! $usuario->perteneceANegocio($producto->negocio_id)) {
            return Response::denyAsNotFound();
        }

        return $this->ajustes->booleano('clientes_descargan_qr')
            ? Response::allow()
            : Response::deny('La descarga de QR no está habilitada para tu cuenta.');
    }

    public function administrar(User $usuario, Producto $producto): bool
    {
        return $usuario->esAdministrador();
    }
}
