<?php

namespace App\Notifications\Messaging;

use App\Models\Messaging\Message;
use Illuminate\Notifications\Notification;

class AcknowledgementReminderNotification extends Notification
{
    public function __construct(public Message $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => 'Recordatorio de acuse pendiente', 'message' => $this->message->subject ?: mb_strimwidth((string) $this->message->body, 0, 120, '…'), 'icon' => 'bx bx-time-five', 'priority' => $this->message->priority === 'urgent' ? 'alta' : 'media', 'action_url' => '/mensajeria/'.$this->message->conversation->public_id.'?message='.$this->message->public_id, 'conversation_id' => $this->message->conversation->public_id, 'message_id' => $this->message->public_id];
    }
}
