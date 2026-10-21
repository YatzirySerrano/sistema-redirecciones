<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;

/**
 * Evita la enumeración de cuentas: si el correo no existe, o si ya se envió
 * un enlace hace poco (límite del broker, que solo ocurre con cuentas
 * existentes), se responde igual que si el enlace se hubiera enviado. Los
 * demás fallos sí se informan.
 */
class RespuestaEnlaceRestablecimientoNoEnviado implements FailedPasswordResetLinkRequestResponse
{
    public function __construct(private readonly string $status) {}

    public function toResponse($request): RedirectResponse
    {
        if (in_array($this->status, [Password::INVALID_USER, Password::RESET_THROTTLED], true)) {
            return back()->with('status', trans(Password::RESET_LINK_SENT));
        }

        throw ValidationException::withMessages(['email' => [trans($this->status)]]);
    }
}
