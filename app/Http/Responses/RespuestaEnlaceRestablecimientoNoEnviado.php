<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;

/**
 * Evita la enumeración de cuentas: si el correo no existe se responde igual
 * que si el enlace se hubiera enviado. Los demás fallos (p. ej. límite de
 * intentos) sí se informan.
 */
class RespuestaEnlaceRestablecimientoNoEnviado implements FailedPasswordResetLinkRequestResponse
{
    public function __construct(private readonly string $status) {}

    public function toResponse($request): RedirectResponse
    {
        if ($this->status === Password::INVALID_USER) {
            return back()->with('status', trans(Password::RESET_LINK_SENT));
        }

        throw ValidationException::withMessages(['email' => [trans($this->status)]]);
    }
}
