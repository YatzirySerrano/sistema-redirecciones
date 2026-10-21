<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use App\Services\Usuarios\SesionesUsuario;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function __construct(
        private readonly RegistradorAuditoria $auditoria,
        private readonly SesionesUsuario $sesiones,
    ) {}

    /**
     * Validate and reset the user's forgotten password.
     *
     * También completa el flujo de invitación: quien define su contraseña con
     * un enlace de correo ya no necesita cambiarla de nuevo.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => $input['password'],
            'debe_cambiar_password' => false,
            'invitacion_aceptada_en' => $user->invitacion_enviada_en !== null && $user->invitacion_aceptada_en === null
                ? now()
                : $user->invitacion_aceptada_en,
        ])->save();

        $this->sesiones->cerrarTodas($user);

        $this->auditoria->registrar(
            accion: 'usuario.contrasena_restablecida',
            descripcion: "{$user->email} restableció su contraseña mediante enlace de correo",
            recurso: $user,
            negocio: $user->negocio_id,
            actor: $user,
        );
    }
}
