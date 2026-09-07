<?php

namespace App\Notifications;

use App\Models\PermissionRequest;
use App\Support\Notifications\NotificationEnvelope;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PermissionRequestStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly PermissionRequest $permissionRequest,
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
        return NotificationEnvelope::make(
            eventKey: sprintf('staff.permission.status:%d:%s:%s', $this->permissionRequest->id, $this->permissionRequest->status, $this->permissionRequest->current_step),
            eventType: 'staff.permission.status_changed',
            module: 'staff_permissions',
            title: $this->subjectLine,
            message: $this->headline,
            resource: ['type' => 'permission_request', 'id' => $this->permissionRequest->id],
            actionUrl: '/staff/permissions',
            icon: 'bx bx-calendar-check',
            priority: in_array($this->permissionRequest->status, ['rechazado', 'observado'], true) ? 'alta' : 'media',
            occurredAt: $this->permissionRequest->updated_at,
            context: ['status' => $this->permissionRequest->status, 'step' => $this->permissionRequest->current_step],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->subjectLine)
            ->greeting('Hola '.($notifiable->name ?? ''))
            ->line($this->headline)
            ->line('Funcionario: '.($this->permissionRequest->staff?->full_name ?? '-'))
            ->line('Tipo: '.($this->permissionRequest->permissionType?->name ?? '-'))
            ->line('Estado actual: '.$this->permissionRequest->status)
            ->line('Periodo: '.$this->permissionRequest->start_date?->format('d/m/Y').' al '.$this->permissionRequest->end_date?->format('d/m/Y'));

        if ($this->comment) {
            $message->line('Comentario: '.$this->comment);
        }

        return $message;
    }
}
