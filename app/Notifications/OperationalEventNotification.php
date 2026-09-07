<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\Notifications\NotificationEnvelope;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OperationalEventNotification extends Notification
{
    use Queueable;

    /**
     * @param  array{type:string,id:int|string,code?:string|null}  $resource
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $eventKey,
        private readonly string $eventType,
        private readonly string $module,
        private readonly string $title,
        private readonly string $message,
        private readonly array $resource,
        private readonly ?string $actionUrl = null,
        private readonly string $icon = 'bx bx-bell',
        private readonly string $priority = 'media',
        private readonly DateTimeInterface|string|null $occurredAt = null,
        private readonly ?User $actor = null,
        private readonly array $context = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return NotificationEnvelope::make(
            eventKey: $this->eventKey,
            eventType: $this->eventType,
            module: $this->module,
            title: $this->title,
            message: $this->message,
            resource: $this->resource,
            actionUrl: $this->actionUrl,
            icon: $this->icon,
            priority: $this->priority,
            occurredAt: $this->occurredAt,
            actor: $this->actor,
            context: $this->context,
        );
    }
}
