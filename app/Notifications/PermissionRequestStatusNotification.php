<?php

namespace App\Notifications;

use App\Models\PermissionRequest;
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
        $message = (new MailMessage())
            ->subject($this->subjectLine)
            ->greeting('Hola ' . ($notifiable->name ?? ''))
            ->line($this->headline)
            ->line('Funcionario: ' . ($this->permissionRequest->staff?->full_name ?? '-'))
            ->line('Tipo: ' . ($this->permissionRequest->permissionType?->name ?? '-'))
            ->line('Estado actual: ' . $this->permissionRequest->status)
            ->line('Periodo: ' . $this->permissionRequest->start_date?->format('d/m/Y') . ' al ' . $this->permissionRequest->end_date?->format('d/m/Y'));

        if ($this->comment) {
            $message->line('Comentario: ' . $this->comment);
        }

        return $message;
    }
}
