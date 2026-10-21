<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invitación para que un usuario defina su contraseña. Reutiliza el broker de
 * restablecimiento de contraseñas de Laravel: el enlace caduca según
 * auth.passwords.users.expire y el token se guarda cifrado (hash).
 */
class InvitacionAcceso extends Notification
{
    public function __construct(public readonly string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->email]);
        $minutos = (int) config('auth.passwords.users.expire');
        $negocio = $notifiable->negocio?->nombre_comercial;

        return (new MailMessage)
            ->subject('Tu acceso al panel de redirecciones')
            ->greeting('Hola, '.$notifiable->name)
            ->line($negocio !== null
                ? "Se creó tu acceso para administrar los enlaces QR y NFC de {$negocio}."
                : 'Se creó tu acceso al panel de administración de redirecciones.')
            ->line('Para entrar por primera vez, define tu contraseña desde el siguiente enlace:')
            ->action('Definir mi contraseña', $url)
            ->line("Este enlace caduca en {$minutos} minutos. Si expira, solicita uno nuevo desde «¿Olvidaste tu contraseña?».")
            ->line('Si no esperabas este mensaje, puedes ignorarlo.');
    }
}
