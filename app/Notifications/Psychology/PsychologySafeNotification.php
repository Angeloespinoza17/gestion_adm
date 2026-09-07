<?php

namespace App\Notifications\Psychology;

use App\Support\Notifications\NotificationEnvelope;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PsychologySafeNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $title, private readonly string $message, private readonly string $url) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return NotificationEnvelope::make(
            eventKey: 'psychology.notice:'.hash('sha256', $this->title.'|'.$this->url.'|'.$this->message),
            eventType: 'psychology.notice',
            module: 'psychology',
            title: $this->title,
            message: $this->message,
            resource: ['type' => 'psychology_notice', 'id' => hash('sha256', $this->title.'|'.$this->url)],
            actionUrl: $this->url,
            icon: 'bx bx-brain',
            priority: str_contains(mb_strtolower($this->title), 'vencid') ? 'alta' : 'media',
        );
    }
}
