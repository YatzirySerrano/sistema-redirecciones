<?php

namespace App\Services\Autorizacion;

use App\Models\Negocio;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Negocio sobre el que opera la petición actual.
 *
 * - Usuario de un negocio: siempre SU negocio (nunca puede elegir otro).
 * - Personal DASTI en las rutas «Diseño para clientes»
 *   (admin/disenar/negocios/{negocio}/...): el negocio de la ruta, fijado por
 *   el middleware DisenarParaNegocio después de comprobar el permiso
 *   `<modulo>.disenar_negocios`. Fuera de esas rutas el personal DASTI no
 *   tiene negocio de trabajo.
 *
 * Es un servicio por petición (scoped): no se comparte entre peticiones.
 */
class NegocioDeTrabajo
{
    private ?Negocio $negocio = null;

    private ?string $modulo = null;

    /**
     * Solo lo invoca DisenarParaNegocio (personal DASTI ya autorizado).
     */
    public function establecer(Negocio $negocio, string $modulo): void
    {
        $this->negocio = $negocio;
        $this->modulo = $modulo;
    }

    public function negocio(Request $request): ?Negocio
    {
        if ($this->negocio !== null) {
            return $this->negocio;
        }

        $usuario = $request->user();

        return $usuario instanceof User && $usuario->esCliente() ? $usuario->negocio : null;
    }

    /**
     * ¿El personal DASTI está trabajando para un negocio (diseño para
     * clientes)?
     */
    public function esDasti(): bool
    {
        return $this->negocio !== null;
    }

    /**
     * Módulo autorizado en el contexto DASTI («fidelidad» o «paginas»).
     */
    public function modulo(): ?string
    {
        return $this->modulo;
    }
}
