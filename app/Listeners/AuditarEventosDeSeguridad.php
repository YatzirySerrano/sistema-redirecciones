<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Str;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

/**
 * Registra en la auditoría los eventos de seguridad de las cuentas que
 * dispara el framework (inicio de sesión, bloqueo por intentos y cambios en
 * la verificación en dos pasos). Nunca guarda contraseñas, códigos ni
 * secretos.
 */
class AuditarEventosDeSeguridad
{
    public function __construct(private readonly RegistradorAuditoria $auditoria) {}

    public function inicioDeSesion(Login $evento): void
    {
        if (! $evento->user instanceof User) {
            return;
        }

        $evento->user->forceFill(['ultimo_acceso_en' => now()])->saveQuietly();

        $this->registrar($evento->user, 'sesion.iniciada', 'Inicio de sesión');
    }

    public function bloqueo(Lockout $evento): void
    {
        $correo = Str::lower((string) $evento->request->input('email'));

        $this->auditoria->registrar(
            accion: 'sesion.bloqueada',
            descripcion: 'Inicio de sesión bloqueado temporalmente por intentos fallidos',
            nuevos: ['correo' => mb_substr($correo, 0, 120)],
        );
    }

    public function dosPasosActivada(TwoFactorAuthenticationConfirmed $evento): void
    {
        $this->registrar($evento->user, 'usuario.2fa_activada', 'Verificación en dos pasos activada');
    }

    public function dosPasosDesactivada(TwoFactorAuthenticationDisabled $evento): void
    {
        $this->registrar($evento->user, 'usuario.2fa_desactivada', 'Verificación en dos pasos desactivada');
    }

    public function codigosRegenerados(RecoveryCodesGenerated $evento): void
    {
        $this->registrar($evento->user, 'usuario.codigos_recuperacion', 'Códigos de recuperación regenerados');
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $eventos): array
    {
        return [
            Login::class => 'inicioDeSesion',
            Lockout::class => 'bloqueo',
            TwoFactorAuthenticationConfirmed::class => 'dosPasosActivada',
            TwoFactorAuthenticationDisabled::class => 'dosPasosDesactivada',
            RecoveryCodesGenerated::class => 'codigosRegenerados',
        ];
    }

    private function registrar(mixed $usuario, string $accion, string $descripcion): void
    {
        if (! $usuario instanceof User) {
            return;
        }

        $this->auditoria->registrar(
            accion: $accion,
            descripcion: "{$descripcion}: {$usuario->email}",
            recurso: $usuario,
            negocio: $usuario->negocio_id,
            actor: $usuario,
        );
    }
}
