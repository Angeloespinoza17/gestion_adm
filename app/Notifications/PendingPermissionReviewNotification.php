<?php

namespace App\Notifications;

use App\Models\PermissionRequest;
use App\Support\Notifications\NotificationEnvelope;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PendingPermissionReviewNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly PermissionRequest $permissionRequest,
        private readonly string $stepLabel,
    ) {}

    public function via(object $notifiable): array
    {
        return empty($notifiable->email) ? ['database'] : ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return NotificationEnvelope::make(
            eventKey: sprintf('staff.permission.review:%d:%s', $this->permissionRequest->id, $this->permissionRequest->current_step),
            eventType: 'staff.permission.review_requested',
            module: 'staff_permissions',
            title: 'Solicitud de permiso pendiente',
            message: 'Tienes una solicitud de permiso pendiente en la etapa '.$this->stepLabel.'.',
            resource: ['type' => 'permission_request', 'id' => $this->permissionRequest->id],
            actionUrl: '/staff/permissions/review',
            icon: 'bx bx-calendar-check',
            priority: 'media',
            occurredAt: $this->permissionRequest->updated_at,
            context: ['status' => $this->permissionRequest->status, 'step' => $this->permissionRequest->current_step],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Solicitud de permiso pendiente de revisión')
            ->greeting('Hola '.($notifiable->name ?? ''))
            ->line('Tienes una solicitud de permiso pendiente de revisión.')
            ->line('Funcionario: '.($this->permissionRequest->staff?->full_name ?? '-'))
            ->line('Tipo: '.($this->permissionRequest->permissionType?->name ?? '-'))
            ->line('Etapa actual: '.$this->stepLabel)
            ->line('Periodo: '.$this->permissionRequest->start_date?->format('d/m/Y').' al '.$this->permissionRequest->end_date?->format('d/m/Y'))
            ->line('Motivo: '.$this->permissionRequest->reason);
    }
}
