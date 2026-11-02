<?php

namespace App\Notifications\Fidelidad;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo a un consumidor de Fidelidad con un enlace temporal de un solo uso.
 * No incluye datos personales ni el progreso.
 */
class RenovarEnlaceFidelidad extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $url, public readonly string $negocio)
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

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Tu nuevo enlace del programa de {$this->negocio}")
            ->greeting('Hola')
            ->line("Pediste recuperar el acceso a tu programa de recompensas de {$this->negocio}.")
            ->line('Abre este enlace para obtener un enlace de consulta nuevo (el anterior dejará de funcionar):')
            ->action('Ver mi progreso', $this->url)
            ->line('El enlace caduca en 60 minutos y solo funciona una vez.')
            ->line('Si no fuiste tú, ignora este mensaje: no se hará ningún cambio.');
    }
}
