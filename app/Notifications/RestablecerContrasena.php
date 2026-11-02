<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Enlace para restablecer la contraseña (flujo de Fortify).
 */
class RestablecerContrasena extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $token)
    {
        $this->afterCommit();
    }

    /**
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
     * Una cuenta suspendida mientras el mensaje esperaba en la cola no lo
     * recibe.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        if (! $notifiable instanceof User) {
            return true;
        }

        return $notifiable->fresh(['negocio'])?->puedeOperar() ?? false;
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $minutos = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Restablece tu contraseña de DASTI Redirecciones')
            ->greeting('Hola, '.$notifiable->name)
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta en DASTI Redirecciones.')
            ->action('Restablecer contraseña', $url)
            ->line("Por seguridad, el enlace funciona una sola vez y caduca en {$minutos} minutos.")
            ->line('Si no solicitaste el cambio, ignora este correo: tu contraseña actual seguirá funcionando.');
    }
}
