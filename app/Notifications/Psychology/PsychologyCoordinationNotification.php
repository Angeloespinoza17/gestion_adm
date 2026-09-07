<?php

namespace App\Notifications\Psychology;

use App\Support\Notifications\NotificationEnvelope;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PsychologyCoordinationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly int $coordinationRequestId,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return NotificationEnvelope::make(
            eventKey: 'psychology.coordination.notice:'.$this->coordinationRequestId.':'.hash('sha256', $this->title),
            eventType: 'psychology.coordination.notice',
            module: 'psychology_coordination',
            title: $this->title,
            message: $this->message,
            resource: ['type' => 'psychology_coordination_request', 'id' => $this->coordinationRequestId],
            actionUrl: '/mis-coordinaciones',
            icon: 'bx bx-conversation',
            priority: 'media',
            context: ['coordination_request_id' => $this->coordinationRequestId],
        ) + ['coordination_request_id' => $this->coordinationRequestId];
    }
}
