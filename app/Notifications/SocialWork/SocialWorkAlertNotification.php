<?php

namespace App\Notifications\SocialWork;

use App\Models\SocialWork\Alert;
use App\Support\Notifications\NotificationEnvelope;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SocialWorkAlertNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Alert $alert) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return NotificationEnvelope::make(
            eventKey: 'social_work.alert.created:'.$this->alert->id,
            eventType: 'social_work.alert.created',
            module: 'social_work',
            title: 'Alerta de Trabajo Social',
            message: 'Existe una alerta social asignada que requiere revisión.',
            resource: ['type' => 'social_work_alert', 'id' => $this->alert->id],
            actionUrl: '/social-work/alerts',
            icon: 'bx bx-bell',
            priority: $this->alert->severity,
            occurredAt: $this->alert->alerted_at ?? $this->alert->created_at,
            context: ['status' => $this->alert->status],
        );
    }
}
