<?php

namespace App\Http\Controllers\Cuenta;

use App\Actions\Usuarios\CambiarContrasenaInicial;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cuenta\ContrasenaInicialRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sustitución obligatoria de la contraseña temporal.
 */
class ContrasenaInicialController extends Controller
{
    public function edit(Request $request): Response|RedirectResponse
    {
        if (! $request->user()?->debe_cambiar_password) {
            return redirect()->route('inicio');
        }

        return Inertia::render('auth/CambiarContrasena', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(ContrasenaInicialRequest $request, CambiarContrasenaInicial $accion): RedirectResponse
    {
        $accion->ejecutar($request->user(), (string) $request->validated('password'), $request->session()->getId());

        $this->notificar('Contraseña actualizada. ¡Bienvenido!');

        return redirect()->route('inicio');
    }
}
