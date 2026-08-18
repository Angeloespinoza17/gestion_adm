<?php

namespace App\Notifications\Messaging;

use App\Models\Messaging\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Message $message)
    {
        $this->onQueue('notifications')->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $ack = $this->message->requires_acknowledgement;

        return ['title' => $ack ? 'Acuse de recibo solicitado' : 'Nuevo mensaje', 'message' => $this->message->subject ?: mb_strimwidth((string) $this->message->body, 0, 120, '…'), 'icon' => $ack ? 'bx bx-check-shield' : 'bx bx-message-rounded-dots', 'priority' => $this->message->priority === 'urgent' ? 'alta' : 'media', 'conversation_id' => $this->message->conversation->public_id, 'message_id' => $this->message->public_id, 'action_url' => '/mensajeria/'.$this->message->conversation->public_id.'?message='.$this->message->public_id];
    }
}
