<?php

namespace App\Events\Messaging;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MessageRead extends MessagingBroadcastEvent implements ShouldBroadcast
{
    use BroadcastsToMessagingUsers;

    public function __construct(public string $conversationId, public array $receipt, public array $recipientIds = []) {}

    public function broadcastAs(): string
    {
        return 'messaging.message.read';
    }

    public function broadcastWith(): array
    {
        return ['receipt' => $this->receipt];
    }
}
