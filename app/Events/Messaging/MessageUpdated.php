<?php

namespace App\Events\Messaging;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MessageUpdated extends MessagingBroadcastEvent implements ShouldBroadcast
{
    use BroadcastsToMessagingUsers;

    public function __construct(public string $conversationId, public array $message, public array $recipientIds = []) {}

    public function broadcastAs(): string
    {
        return 'messaging.message.updated';
    }

    public function broadcastWith(): array
    {
        return ['message' => $this->message];
    }
}
