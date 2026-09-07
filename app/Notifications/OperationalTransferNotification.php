<?php

namespace App\Notifications;

use App\Models\Operational\OperationalTransferRequest;
use App\Models\User;
use App\Support\Notifications\NotificationEnvelope;
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
        return empty($notifiable->email) ? ['database'] : ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        $eventKey = sprintf(
            'operational.transfer.status:%d:%s',
            $this->transfer->id,
            $this->transfer->approval_status,
        );
        $actionUrl = $notifiable instanceof User && $notifiable->hasPermission('gestionar_traslados_operativos')
            ? '/operational/transfers/management'
            : ($notifiable instanceof User && $notifiable->hasPermission('visar_traslados_operativos')
                ? '/operational/transfers/review'
                : '/operational/transfers');

        return NotificationEnvelope::make(
            eventKey: $eventKey,
            eventType: 'operational.transfer.status_changed',
            module: 'operational_transfers',
            title: $this->subjectLine,
            message: $this->headline.' Folio '.$this->transfer->folio.'.',
            resource: [
                'type' => 'operational_transfer_request',
                'id' => $this->transfer->id,
                'code' => $this->transfer->folio,
            ],
            actionUrl: $actionUrl,
            icon: 'bx bx-bus',
            priority: $this->transfer->urgent ? 'alta' : 'media',
            occurredAt: $this->transfer->updated_at,
            context: ['status' => $this->transfer->approval_status],
        );
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
