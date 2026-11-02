<?php

namespace App\Http\Middleware;

use App\Models\Servicio;
use App\Models\User;
use App\Services\Servicios\AccesoServicios;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige que el NEGOCIO del usuario tenga contratado el servicio (los
 * permisos del rol no bastan).
 *
 * Uso: `RequiereServicio:fidelidad` (consulta: activa, restringida o
 * vencida) o `RequiereServicio:fidelidad,operar` (solo activa y vigente).
 */
class RequiereServicio
{
    public function __construct(private readonly AccesoServicios $acceso) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $servicio, string $modo = 'consultar'): Response
    {
        $usuario = $request->user();
        $negocio = $usuario instanceof User ? $usuario->negocio : null;

        abort_if($negocio === null || $servicio !== Servicio::FIDELIDAD, 403, 'No tienes permiso para realizar esta acción.');

        if ($modo === 'operar' && ! $this->acceso->puedeOperarFidelidad($negocio)) {
            abort(403, $this->acceso->puedeConsultarFidelidad($negocio)
                ? 'Fidelidad está en modo de solo lectura: el servicio venció o está en revisión. Contacta a DASTI.'
                : 'Tu negocio no tiene Fidelidad contratado.');
        }

        if (! $this->acceso->puedeConsultarFidelidad($negocio)) {
            if ($request->expectsJson()) {
                abort(403, 'Tu negocio no tiene Fidelidad contratado.');
            }

            Inertia::flash('toast', ['type' => 'info', 'message' => 'Fidelidad DASTI no está activo para tu negocio.']);

            return redirect()->route('cliente.fidelidad.contratacion');
        }

        return $next($request);
    }
}
