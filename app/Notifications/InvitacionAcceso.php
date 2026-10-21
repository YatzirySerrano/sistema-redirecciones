<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invitación para que un usuario defina su contraseña.
 *
 * El enlace es una URL firmada de un solo uso (App\Services\Usuarios\
 * EnlaceInvitacion); no contiene ni expone ningún token almacenado.
 */
class InvitacionAcceso extends Notification
{
    public function __construct(
        public readonly string $url,
        public readonly int $horasValidez,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $negocio = $notifiable->negocio?->nombre_comercial;
        $vigencia = $this->horasValidez % 24 === 0
            ? ($this->horasValidez / 24).' '.($this->horasValidez === 24 ? 'día' : 'días')
            : $this->horasValidez.' horas';

        return (new MailMessage)
            ->subject('Tu acceso al panel de redirecciones')
            ->greeting('Hola, '.$notifiable->name)
            ->line($negocio !== null
                ? "Se creó tu acceso para administrar los enlaces QR y NFC de {$negocio}."
                : 'Se creó tu acceso al panel de administración de redirecciones.')
            ->line('Para entrar por primera vez, define tu contraseña desde el siguiente enlace:')
            ->action('Definir mi contraseña', $this->url)
            ->line("El enlace funciona una sola vez y caduca en {$vigencia}. Si expira, pide a DASTI que te envíe uno nuevo.")
            ->line('Si no esperabas este mensaje, puedes ignorarlo.');
    }
}
