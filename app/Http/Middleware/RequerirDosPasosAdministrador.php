<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige la verificación en dos pasos (Fortify) a los administradores antes
 * de usar el panel de DASTI, porque un administrador puede cambiar el
 * destino de todos los productos.
 *
 * No bloquea el acceso: redirige a Ajustes › Seguridad (fuera del panel
 * administrativo), donde se activa y confirma con los flujos de Fortify,
 * incluidos los códigos de recuperación. Se controla con
 * dasti.exigir_2fa_administradores (activo por defecto en producción).
 */
class RequerirDosPasosAdministrador
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! config('dasti.exigir_2fa_administradores')
            || ! $usuario instanceof User
            || ! $usuario->esAdministrador()
            || $usuario->two_factor_confirmed_at !== null) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Activa la verificación en dos pasos para usar el panel de administración.');
        }

        Inertia::flash('toast', [
            'type' => 'warning',
            'message' => 'Por seguridad, activa la verificación en dos pasos para usar el panel de administración.',
        ]);

        return redirect()->route('security.edit');
    }
}
