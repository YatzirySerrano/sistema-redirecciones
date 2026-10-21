<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Obliga a sustituir la contraseña temporal antes de usar el panel.
 */
class RequerirContrasenaDefinitiva
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario instanceof User && $usuario->debe_cambiar_password && ! $request->routeIs('contrasena-inicial.*', 'logout')) {
            return redirect()->route('contrasena-inicial.edit');
        }

        return $next($request);
    }
}
