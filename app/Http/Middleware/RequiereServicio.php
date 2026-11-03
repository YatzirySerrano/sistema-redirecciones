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
 * permisos del rol no bastan). Cada servicio adicional se comprueba por
 * separado.
 *
 * Uso: `RequiereServicio:fidelidad` (consulta: activa, restringida o
 * vencida) o `RequiereServicio:pagina_enlaces,operar` (solo activa y
 * vigente).
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

        abort_if($negocio === null || ! Servicio::esAdicional($servicio), 403, 'No tienes permiso para realizar esta acción.');

        $nombre = $servicio === Servicio::FIDELIDAD ? 'Fidelidad' : 'Página de enlaces';

        if ($modo === 'operar' && ! $this->acceso->puedeOperar($negocio, $servicio)) {
            abort(403, $this->acceso->puedeConsultar($negocio, $servicio)
                ? "{$nombre} está en modo de solo lectura: el servicio venció o está en revisión. Contacta a DASTI."
                : "Tu negocio no tiene {$nombre} contratado.");
        }

        if (! $this->acceso->puedeConsultar($negocio, $servicio)) {
            if ($request->expectsJson()) {
                abort(403, "Tu negocio no tiene {$nombre} contratado.");
            }

            Inertia::flash('toast', ['type' => 'info', 'message' => "{$nombre} no está activo para tu negocio. Aquí puedes conocerlo y contratarlo."]);

            return redirect()->route('cliente.servicios.show', $servicio);
        }

        return $next($request);
    }
}
