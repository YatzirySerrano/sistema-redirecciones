<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión de usuarios suspendidos o de clientes cuyo negocio fue
 * suspendido, aunque ya hubieran iniciado sesión antes de la suspensión.
 */
class VerificarCuentaActiva
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario instanceof User && ! $usuario->puedeOperar()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Tu acceso está suspendido. Si crees que es un error, contacta a DASTI.');
        }

        return $next($request);
    }
}
