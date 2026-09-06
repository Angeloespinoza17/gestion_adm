<?php

namespace App\Notifications\Messaging;

use App\Models\Messaging\Message;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Ramsey\Uuid\Uuid;

class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Message $message)
    {
        $this->onQueue('notifications')->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return config('messaging.enabled') && $notifiable instanceof User && $notifiable->canUseMessaging()
            ? ['database']
            : [];
    }

    public function toArray(object $notifiable): array
    {
        return self::databasePayload($this->message);
    }

    public static function databasePayload(Message $message): array
    {
        $ack = $message->requires_acknowledgement;

        return ['title' => $ack ? 'Acuse de recibo solicitado' : 'Nuevo mensaje', 'message' => $message->subject ?: mb_strimwidth((string) $message->body, 0, 120, '…'), 'icon' => $ack ? 'bx bx-check-shield' : 'bx bx-message-rounded-dots', 'priority' => $message->priority === 'urgent' ? 'alta' : 'media', 'conversation_id' => $message->conversation->public_id, 'message_id' => $message->public_id, 'action_url' => '/mensajeria/'.$message->conversation->public_id.'?message='.$message->public_id];
    }

    public static function deterministicId(string $messagePublicId, int $userId): string
    {
        return Uuid::uuid5(
            Uuid::NAMESPACE_URL,
            'skote:messaging:new-message:'.$messagePublicId.':'.$userId
        )->toString();
    }
}
