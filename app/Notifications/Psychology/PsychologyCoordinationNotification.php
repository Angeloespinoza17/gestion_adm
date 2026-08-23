<?php

namespace App\Notifications\Psychology;

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
        return [
            'title' => $this->title,
            'message' => $this->message,
            'action_url' => '/mis-coordinaciones',
            'icon' => 'bx bx-conversation',
            'priority' => 'media',
            'module' => 'psychology_coordination',
            'coordination_request_id' => $this->coordinationRequestId,
        ];
    }
}
