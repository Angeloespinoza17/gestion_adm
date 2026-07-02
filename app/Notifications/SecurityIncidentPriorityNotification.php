<?php

namespace App\Notifications;

use App\Models\Security\SecurityIncident;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SecurityIncidentPriorityNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly SecurityIncident $incident,
    ) {
    }

    public function via(object $notifiable): array
    {
        if (empty($notifiable->email)) {
            return [];
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $priority = collect(SecurityIncident::PRIORITY_OPTIONS)
            ->firstWhere('value', $this->incident->priority)['label'] ?? strtoupper($this->incident->priority);

        return (new MailMessage())
            ->subject("Alerta de seguridad {$priority}")
            ->greeting('Hola ' . ($notifiable->name ?? ''))
            ->line('Se registró una novedad de seguridad que requiere atención.')
            ->line('Prioridad: ' . $priority)
            ->line('Título: ' . $this->incident->title)
            ->line('Sector: ' . ($this->incident->sector_name ?: $this->incident->shift?->coverage_label ?: '-'))
            ->line('Reportado por: ' . ($this->incident->reportedBy?->name ?: $this->incident->shift?->staff?->full_name ?: '-'))
            ->line('Descripción: ' . $this->incident->description);
    }
}
