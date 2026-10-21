<?php

namespace App\Services\Usuarios;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Enlaces de invitación de un solo uso.
 *
 * No se guarda ningún token: el enlace es una URL firmada (APP_KEY) con
 * caducidad y una huella ligada al hash de la contraseña y a la fecha del
 * último envío. Al definir la contraseña, o al reenviar la invitación, la
 * huella cambia y los enlaces anteriores dejan de funcionar.
 */
class EnlaceInvitacion
{
    public function generar(User $usuario): string
    {
        return URL::temporarySignedRoute(
            'invitacion.show',
            CarbonImmutable::now()->addHours($this->horasValidez()),
            ['usuario' => $usuario->id, 'huella' => $this->huella($usuario)],
        );
    }

    public function horasValidez(): int
    {
        return max(1, (int) config('dasti.horas_validez_invitacion'));
    }

    /**
     * La firma debe ser válida y vigente, y la huella corresponder al estado
     * actual del usuario (no usada ni reemplazada por un reenvío).
     */
    public function esValido(Request $request, User $usuario): bool
    {
        return $request->hasValidSignature()
            && $usuario->invitacion_enviada_en !== null
            && $usuario->invitacion_aceptada_en === null
            && hash_equals($this->huella($usuario), (string) $request->query('huella'));
    }

    private function huella(User $usuario): string
    {
        $datos = $usuario->id.'|'.$usuario->getAuthPassword().'|'.$usuario->invitacion_enviada_en?->getTimestamp();

        return substr(hash_hmac('sha256', $datos, (string) config('app.key')), 0, 40);
    }
}
