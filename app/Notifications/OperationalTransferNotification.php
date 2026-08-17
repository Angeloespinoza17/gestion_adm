<?php

namespace App\Notifications;

use App\Models\Operational\OperationalTransferRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OperationalTransferNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly OperationalTransferRequest $transfer,
        private readonly string $subjectLine,
        private readonly string $headline,
        private readonly ?string $comment = null,
    ) {}

    public function via(object $notifiable): array
    {
        return empty($notifiable->email) ? [] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->subjectLine)
            ->greeting('Hola '.($notifiable->name ?? ''))
            ->line($this->headline)
            ->line('Folio: '.$this->transfer->folio)
            ->line('Actividad: '.$this->transfer->activity_name)
            ->line('Fecha: '.$this->transfer->transport_date?->format('d/m/Y'))
            ->line('Destino: '.$this->transfer->destination)
            ->line('Estado: '.$this->transfer->approval_status);

        if ($this->comment) {
            $message->line('Comentario: '.$this->comment);
        }

        return $message;
    }
}
