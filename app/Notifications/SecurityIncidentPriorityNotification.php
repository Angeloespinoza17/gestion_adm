<?php

namespace App\Notifications;

use App\Models\Security\SecurityIncident;
use App\Support\Notifications\NotificationEnvelope;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SecurityIncidentPriorityNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly SecurityIncident $incident,
    ) {}

    public function via(object $notifiable): array
    {
        return empty($notifiable->email) ? ['database'] : ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return NotificationEnvelope::make(
            eventKey: 'security.incident.priority:'.$this->incident->id,
            eventType: 'security.incident.priority',
            module: 'security',
            title: $this->incident->priority === 'critica' ? 'Alerta crítica de seguridad' : 'Alerta prioritaria de seguridad',
            message: 'Se registró una novedad de seguridad que requiere revisión inmediata.',
            resource: ['type' => 'security_incident', 'id' => $this->incident->id],
            actionUrl: '/security/incidents',
            icon: 'bx bx-shield-quarter',
            priority: $this->incident->priority,
            occurredAt: $this->incident->occurred_at ?? $this->incident->created_at,
            context: ['status' => $this->incident->status?->code],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $priority = collect(SecurityIncident::PRIORITY_OPTIONS)
            ->firstWhere('value', $this->incident->priority)['label'] ?? strtoupper($this->incident->priority);

        return (new MailMessage)
            ->subject("Alerta de seguridad {$priority}")
            ->greeting('Hola '.($notifiable->name ?? ''))
            ->line('Se registró una novedad de seguridad que requiere atención.')
            ->line('Prioridad: '.$priority)
            ->line('Título: '.$this->incident->title)
            ->line('Sector: '.($this->incident->sector_name ?: $this->incident->shift?->coverage_label ?: '-'))
            ->line('Reportado por: '.($this->incident->reportedBy?->name ?: $this->incident->shift?->staff?->full_name ?: '-'))
            ->line('Descripción: '.$this->incident->description);
    }
}
