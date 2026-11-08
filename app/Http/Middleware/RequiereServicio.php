<?php

namespace App\Http\Middleware;

use App\Models\Servicio;
use App\Services\Autorizacion\NegocioDeTrabajo;
use App\Services\Servicios\AccesoServicios;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige que el NEGOCIO de trabajo (el del usuario o, en «Diseño para
 * clientes», el elegido por el personal DASTI) tenga contratado el servicio (los
 * permisos del rol no bastan). Cada servicio adicional se comprueba por
 * separado.
 *
 * Uso: `RequiereServicio:fidelidad` (consulta: activa, restringida o
 * vencida) o `RequiereServicio:pagina_enlaces,operar` (solo activa y
 * vigente).
 */
class RequiereServicio
{
    public function __construct(
        private readonly AccesoServicios $acceso,
        private readonly NegocioDeTrabajo $contexto,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $servicio, string $modo = 'consultar'): Response
    {
        $negocio = $this->contexto->negocio($request);
        $dasti = $this->contexto->esDasti();

        abort_if($negocio === null || ! Servicio::esAdicional($servicio), 403, 'No tienes permiso para realizar esta acción.');

        $nombre = $servicio === Servicio::FIDELIDAD ? 'Fidelidad' : 'Página de enlaces';

        if ($modo === 'operar' && ! $this->acceso->puedeOperar($negocio, $servicio)) {
            abort(403, match (true) {
                $this->acceso->puedeConsultar($negocio, $servicio) => $dasti
                    ? "{$nombre} de este negocio está en solo lectura (venció o está en revisión): no se pueden guardar cambios."
                    : "{$nombre} está en modo de solo lectura: el servicio venció o está en revisión. Contacta a DASTI.",
                $dasti => "Este negocio no tiene {$nombre} contratado.",
                default => "Tu negocio no tiene {$nombre} contratado.",
            });
        }

        if (! $this->acceso->puedeConsultar($negocio, $servicio)) {
            if ($request->expectsJson()) {
                abort(403, $dasti ? "Este negocio no tiene {$nombre} contratado." : "Tu negocio no tiene {$nombre} contratado.");
            }

            if ($dasti) {
                Inertia::flash('toast', ['type' => 'info', 'message' => "«{$negocio->nombre_comercial}» no tiene {$nombre} contratado. Actívalo desde Servicios antes de diseñar."]);

                return redirect()->route('admin.disenos.index');
            }

            // Sin «Mis servicios» (servicios.ver) no se le lleva a la
            // contratación: vuelve al inicio con la explicación.
            if (! $request->user()?->tienePermiso('servicios.ver')) {
                Inertia::flash('toast', ['type' => 'info', 'message' => "{$nombre} no está activo para tu negocio. Si te interesa, contacta a DASTI."]);

                return redirect()->route('cliente.inicio');
            }

            Inertia::flash('toast', ['type' => 'info', 'message' => "{$nombre} no está activo para tu negocio. Aquí puedes conocerlo y contratarlo."]);

            return redirect()->route('cliente.servicios.show', $servicio);
        }

        return $next($request);
    }
}
