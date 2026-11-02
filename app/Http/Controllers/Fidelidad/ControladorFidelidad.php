<?php

namespace App\Http\Controllers\Fidelidad;

use App\Http\Controllers\Controller;
use App\Models\Negocio;
use App\Models\User;
use App\Services\Servicios\AccesoServicios;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Base de los controladores del panel de Fidelidad del negocio. Todo
 * registro se comprueba contra el negocio del usuario: uno ajeno responde
 * 404 (no se confirma su existencia).
 */
abstract class ControladorFidelidad extends Controller
{
    protected function usuario(Request $request): User
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return $usuario;
    }

    protected function negocio(Request $request): Negocio
    {
        $negocio = $this->usuario($request)->negocio;
        abort_if($negocio === null, 404);

        return $negocio;
    }

    /**
     * @template T of Model
     *
     * @param  T  $modelo
     * @return T
     */
    protected function delNegocio(Request $request, Model $modelo): Model
    {
        abort_unless((int) $modelo->getAttribute('negocio_id') === $this->usuario($request)->negocio_id, 404);

        return $modelo;
    }

    /**
     * Estado del servicio y capacidades del usuario para la interfaz.
     *
     * @return array<string, mixed>
     */
    protected function contexto(Request $request): array
    {
        $usuario = $this->usuario($request);
        $estado = app(AccesoServicios::class)->fidelidad($this->negocio($request));
        $opera = $estado['activo'] && $this->negocio($request)->estaActivo();

        return [
            'servicio' => $estado,
            'puede' => [
                'visitas' => $opera && $usuario->tienePermiso('fidelidad.visitas'),
                'canjear' => $opera && $usuario->tienePermiso('fidelidad.canjear'),
                'consumidores' => $opera && $usuario->tienePermiso('fidelidad.consumidores'),
                'credenciales' => $opera && $usuario->tienePermiso('fidelidad.credenciales'),
                'ajustes' => $opera && $usuario->tienePermiso('fidelidad.ajustes'),
                'campanas' => $opera && $usuario->tienePermiso('fidelidad.campanas'),
                'personal' => $usuario->tienePermiso('fidelidad.personal'),
                'auditoria' => $usuario->tienePermiso('fidelidad.auditoria'),
            ],
        ];
    }
}
