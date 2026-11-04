<?php

namespace App\Http\Middleware;

use App\Models\Negocio;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use App\Services\Autorizacion\NegocioDeTrabajo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * «Diseño para clientes»: el personal DASTI abre el editor de un negocio
 * concreto. Exige (en servidor, no solo en la interfaz):
 *
 * - cuenta de plataforma (las rutas ya pasan por `can:administrar-plataforma`);
 * - el permiso `<modulo>.disenar_negocios` (fidelidad o paginas);
 * - un negocio existente en la ruta.
 *
 * Fija ese negocio como negocio de trabajo de la petición. La contratación
 * del servicio se sigue comprobando después con RequiereServicio: un
 * permiso nunca sustituye la contratación.
 *
 * Uso: `DisenarParaNegocio:fidelidad` o `DisenarParaNegocio:paginas`.
 */
class DisenarParaNegocio
{
    public function __construct(
        private readonly NegocioDeTrabajo $contexto,
        private readonly RegistradorAuditoria $auditoria,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $modulo): Response
    {
        $usuario = $request->user();
        $negocio = $request->route('negocio');

        abort_unless(in_array($modulo, ['fidelidad', 'paginas'], true), 404);
        abort_unless(
            $usuario instanceof User && $usuario->esAdministrador() && $usuario->tienePermiso("{$modulo}.disenar_negocios"),
            403,
            'No tienes permiso para diseñar para los negocios.',
        );

        if (! $negocio instanceof Negocio) {
            $negocio = Negocio::query()->find((int) $negocio);
        }

        abort_if($negocio === null, 404);

        $this->contexto->establecer($negocio, $modulo);

        // Los controladores son los mismos del panel del negocio: el negocio
        // llega por el contexto, no como parámetro de la acción.
        $request->route()?->forgetParameter('negocio');

        $respuesta = $next($request);

        // Abrir el editor de un negocio queda en su auditoría (las acciones
        // de guardar y publicar ya se registran por separado).
        if ($request->isMethod('GET') && ! $request->expectsJson() && $respuesta->getStatusCode() === 200) {
            $this->auditoria->registrar(
                accion: 'diseno.acceso_dasti',
                descripcion: $modulo === 'fidelidad' ? 'Editor de Fidelidad abierto' : 'Editor de Página de enlaces abierto',
                negocio: $negocio,
                nuevos: ['modulo' => $modulo],
                actor: $usuario,
            );
        }

        return $respuesta;
    }
}
