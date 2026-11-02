<?php

namespace App\Policies;

use App\Models\Producto;
use App\Models\User;
use App\Services\Configuracion\AjustesPlataforma;
use Illuminate\Auth\Access\Response;

/**
 * Autorización sobre productos: combina el PERMISO (rol dinámico) con el
 * ALCANCE de la cuenta (propiedad).
 *
 * - Personal de DASTI: alcance global; basta el permiso.
 * - Clientes: primero se comprueba la propiedad. Cualquier producto ajeno se
 *   responde como inexistente (404) en lugar de 403, para no confirmar la
 *   existencia de recursos de otros negocios a quien manipula
 *   identificadores. Un permiso nunca amplía ese alcance.
 */
class ProductoPolicy
{
    private const SIN_PERMISO = 'No tienes permiso para realizar esta acción.';

    private const SUSPENDIDO = 'Este producto está suspendido. Contacta a DASTI para reactivarlo.';

    public function __construct(private readonly AjustesPlataforma $ajustes) {}

    public function ver(User $usuario, Producto $producto): Response
    {
        return $this->conPermiso($usuario, $producto, 'productos.ver');
    }

    public function cambiarDestino(User $usuario, Producto $producto): Response
    {
        $respuesta = $this->conPermiso($usuario, $producto, 'redirecciones.modificar_destino');

        if ($respuesta->denied() || $usuario->esAdministrador()) {
            return $respuesta;
        }

        return $producto->admiteCambiosDelCliente() ? Response::allow() : Response::deny(self::SUSPENDIDO);
    }

    public function descargarQr(User $usuario, Producto $producto): Response
    {
        $respuesta = $this->conPermiso($usuario, $producto, 'qr.descargar');

        if ($respuesta->denied() || $usuario->esAdministrador()) {
            return $respuesta;
        }

        return $this->ajustes->booleano('clientes_descargan_qr')
            ? Response::allow()
            : Response::deny('La descarga de QR no está habilitada para tu cuenta.');
    }

    /**
     * Guardar el diseño del QR: además del permiso de descarga, el cliente
     * solo lo modifica mientras el producto y su negocio estén activos.
     */
    public function disenarQr(User $usuario, Producto $producto): Response
    {
        $descarga = $this->descargarQr($usuario, $producto);

        if ($descarga->denied()) {
            return $descarga;
        }

        $respuesta = $this->conPermiso($usuario, $producto, 'qr.disenar');

        if ($respuesta->denied() || $usuario->esAdministrador()) {
            return $respuesta;
        }

        return $producto->admiteCambiosDelCliente() ? Response::allow() : Response::deny(self::SUSPENDIDO);
    }

    /**
     * Propiedad (clientes) y después permiso.
     */
    private function conPermiso(User $usuario, Producto $producto, string $permiso): Response
    {
        if (! $usuario->esAdministrador() && ! $usuario->perteneceANegocio($producto->negocio_id)) {
            return Response::denyAsNotFound();
        }

        return $usuario->tienePermiso($permiso) ? Response::allow() : Response::deny(self::SIN_PERMISO);
    }
}
