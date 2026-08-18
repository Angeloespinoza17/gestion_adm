<?php

namespace App\Events\Messaging;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MessageDeleted extends MessagingBroadcastEvent implements ShouldBroadcast
{
    public function __construct(public string $conversationId, public string $messageId, public string $deletedAt) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('messaging.conversation.'.$this->conversationId)];
    }

    public function broadcastAs(): string
    {
        return 'messaging.message.deleted';
    }

    public function broadcastWith(): array
    {
        return ['conversation_id' => $this->conversationId, 'message_id' => $this->messageId, 'deleted_at' => $this->deletedAt];
    }
}
