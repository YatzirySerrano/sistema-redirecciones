<?php

namespace App\Notifications\Paginas;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso al negocio (nunca al visitante) de un mensaje nuevo recibido por el
 * formulario de contacto. No incluye el contenido: se lee en el panel.
 */
class NuevoMensajePagina extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tienes un mensaje nuevo en tu página de enlaces')
            ->greeting('¡Hola!')
            ->line('Un visitante te escribió desde el formulario de contacto de tu página de enlaces.')
            ->action('Leer el mensaje', route('cliente.paginas.mensajes.index'))
            ->line('Por privacidad, el contenido del mensaje solo se muestra en tu panel.');
    }
}
