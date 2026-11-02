<?php

namespace App\Notifications;

use App\Actions\Usuarios\EnviarInvitacion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Throwable;

/**
 * Enlace de acceso (invitación) para definir la contraseña por primera vez.
 *
 * Se envía en cola, después de confirmar la transacción. Si el servidor de
 * correo falla en todos los intentos, failed() marca la invitación como
 * fallida y lo registra en la auditoría para que DASTI pueda reenviarla.
 */
class InvitacionAcceso extends Notification implements ShouldQueue
{
    use Queueable;

    /** Intentos de entrega antes de marcar el envío como fallido. */
    public int $tries = 3;

    public function __construct(
        public readonly string $url,
        public readonly int $horasValidez,
        public readonly ?int $usuarioId = null,
        public readonly ?int $emitidaEn = null,
    ) {
        $this->afterCommit();
    }

    /**
     * Espera entre reintentos (segundos).
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * No se envía un enlace que ya fue sustituido por uno más reciente, ni a
     * cuentas suspendidas o que ya aceptaron la invitación mientras el
     * mensaje esperaba en la cola.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        if (! $notifiable instanceof User) {
            return true;
        }

        $usuario = $notifiable->fresh(['negocio']);

        if ($usuario === null || ! $usuario->puedeOperar() || $usuario->invitacion_aceptada_en !== null) {
            return false;
        }

        return $this->emitidaEn === null
            || $usuario->invitacion_enviada_en?->getTimestamp() === $this->emitidaEn;
    }

    public function toMail(User $notifiable): MailMessage
    {
        $negocio = $notifiable->negocio?->nombre_comercial;

        return (new MailMessage)
            ->subject('Tu acceso a DASTI Redirecciones')
            ->greeting('Hola, '.$notifiable->name)
            ->line($negocio !== null
                ? "DASTI creó tu acceso para administrar los enlaces de los códigos QR y tarjetas NFC de {$negocio}."
                : 'DASTI creó tu acceso al panel de administración de redirecciones QR y NFC.')
            ->line('Para entrar por primera vez, define tu contraseña con el siguiente botón:')
            ->action('Definir mi contraseña', $this->url)
            ->line("Por seguridad, el enlace funciona una sola vez y caduca en {$this->vigencia()}. Si expira, pide a DASTI que te envíe uno nuevo.")
            ->line('Si no esperabas este correo, puedes ignorarlo: nadie podrá entrar sin usar este enlace.');
    }

    /**
     * Todos los intentos fallaron (p. ej. error SMTP).
     */
    public function failed(Throwable $error): void
    {
        $usuario = $this->usuarioId !== null ? User::query()->find($this->usuarioId) : null;

        if ($usuario !== null) {
            app(EnviarInvitacion::class)->registrarFallo($usuario, $error);
        }
    }

    private function vigencia(): string
    {
        return $this->horasValidez % 24 === 0
            ? ($this->horasValidez / 24).' '.($this->horasValidez === 24 ? 'día' : 'días')
            : $this->horasValidez.' '.($this->horasValidez === 1 ? 'hora' : 'horas');
    }
}
