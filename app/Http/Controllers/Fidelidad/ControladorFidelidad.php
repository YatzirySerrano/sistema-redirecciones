<?php

namespace App\Http\Controllers\Fidelidad;

use App\Http\Controllers\Controller;
use App\Models\Negocio;
use App\Models\User;
use App\Services\Autorizacion\NegocioDeTrabajo;
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

    /**
     * Negocio de trabajo: el del usuario o, para el personal DASTI en
     * «Diseño para clientes», el negocio autorizado de la ruta.
     */
    protected function negocio(Request $request): Negocio
    {
        $negocio = app(NegocioDeTrabajo::class)->negocio($request);
        abort_if($negocio === null, 404);

        return $negocio;
    }

    /**
     * ¿Es el personal DASTI diseñando para el negocio?
     */
    protected function esDasti(): bool
    {
        return app(NegocioDeTrabajo::class)->esDasti();
    }

    /**
     * @template T of Model
     *
     * @param  T  $modelo
     * @return T
     */
    protected function delNegocio(Request $request, Model $modelo): Model
    {
        abort_unless((int) $modelo->getAttribute('negocio_id') === $this->negocio($request)->id, 404);

        return $modelo;
    }

    /**
     * Datos del aviso «Estás diseñando para…» (solo personal DASTI).
     *
     * @return array{negocio_id: int, negocio: string, volver: string}|null
     */
    protected function contextoDasti(Request $request): ?array
    {
        if (! $this->esDasti()) {
            return null;
        }

        $negocio = $this->negocio($request);

        return [
            'negocio_id' => $negocio->id,
            'negocio' => $negocio->nombre_comercial,
            'volver' => route('admin.disenos.index', ['negocio' => $negocio->id], absolute: false),
        ];
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
            'contexto_dasti' => $this->contextoDasti($request),
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
