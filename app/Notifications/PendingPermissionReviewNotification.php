<?php

namespace App\Notifications;

use App\Models\PermissionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PendingPermissionReviewNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly PermissionRequest $permissionRequest,
        private readonly string $stepLabel,
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
        return (new MailMessage())
            ->subject('Solicitud de permiso pendiente de revisión')
            ->greeting('Hola ' . ($notifiable->name ?? ''))
            ->line('Tienes una solicitud de permiso pendiente de revisión.')
            ->line('Funcionario: ' . ($this->permissionRequest->staff?->full_name ?? '-'))
            ->line('Tipo: ' . ($this->permissionRequest->permissionType?->name ?? '-'))
            ->line('Etapa actual: ' . $this->stepLabel)
            ->line('Periodo: ' . $this->permissionRequest->start_date?->format('d/m/Y') . ' al ' . $this->permissionRequest->end_date?->format('d/m/Y'))
            ->line('Motivo: ' . $this->permissionRequest->reason);
    }
}
