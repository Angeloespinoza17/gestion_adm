<?php

namespace App\Events\Messaging;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MessageReactionUpdated extends MessagingBroadcastEvent implements ShouldBroadcast
{
    use BroadcastsToMessagingUsers;

    public function __construct(public string $conversationId, public array $reaction, public array $recipientIds = []) {}

    public function broadcastAs(): string
    {
        return 'messaging.message.reaction.updated';
    }

    public function broadcastWith(): array
    {
        return ['reaction' => $this->reaction];
    }
}
