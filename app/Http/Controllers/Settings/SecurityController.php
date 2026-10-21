<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use App\Services\Usuarios\SesionesUsuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        $props = [
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();

            $props['twoFactorEnabled'] = $request->user()->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        return Inertia::render('settings/Security', $props);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request, SesionesUsuario $sesiones, RegistradorAuditoria $auditoria): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $usuario->forceFill([
            'password' => $request->password,
            'debe_cambiar_password' => false,
        ])->save();

        // Las demás sesiones (otros dispositivos) se cierran; esta continúa.
        $sesiones->cerrarTodas($usuario, conservar: $request->session()->getId());

        $auditoria->registrar(
            accion: 'usuario.contrasena_cambiada',
            descripcion: "{$usuario->email} cambió su contraseña",
            recurso: $usuario,
            negocio: $usuario->negocio_id,
            actor: $usuario,
        );

        $this->notificar('Contraseña actualizada. Se cerraron tus sesiones en otros dispositivos.');

        return back();
    }
}
