<?php

namespace App\Http\Controllers\Cuenta;

use App\Actions\Usuarios\AceptarInvitacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cuenta\AceptarInvitacionRequest;
use App\Models\User;
use App\Services\Usuarios\EnlaceInvitacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Aceptación de la invitación de acceso (enlace firmado de un solo uso).
 *
 * Un enlace caducado, ya usado, reemplazado por un reenvío o de una cuenta
 * suspendida muestra la misma pantalla neutra, sin confirmar datos.
 */
class InvitacionController extends Controller
{
    public function show(Request $request, User $usuario, EnlaceInvitacion $enlaces): Response
    {
        if (! $this->vigente($request, $usuario, $enlaces)) {
            return Inertia::render('auth/AceptarInvitacion', ['valida' => false]);
        }

        return Inertia::render('auth/AceptarInvitacion', [
            'valida' => true,
            'nombre' => $usuario->name,
            'email' => $usuario->email,
            'accion' => $request->fullUrl(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(AceptarInvitacionRequest $request, User $usuario, EnlaceInvitacion $enlaces, AceptarInvitacion $accion): RedirectResponse
    {
        if (! $this->vigente($request, $usuario, $enlaces)) {
            return redirect()->to($request->fullUrl());
        }

        $accion->ejecutar($usuario, (string) $request->validated('password'));

        Auth::guard('web')->logout();
        Auth::guard('web')->login($usuario);
        $request->session()->regenerate();

        $this->notificar('Contraseña definida. Bienvenido al panel.');

        return redirect()->route('inicio');
    }

    private function vigente(Request $request, User $usuario, EnlaceInvitacion $enlaces): bool
    {
        $usuario->loadMissing('negocio');

        return $enlaces->esValido($request, $usuario) && $usuario->puedeOperar();
    }
}
