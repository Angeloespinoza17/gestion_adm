<?php

namespace App\Events\Messaging;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MessageAcknowledged extends MessagingBroadcastEvent implements ShouldBroadcast
{
    use BroadcastsToMessagingUsers;

    public function __construct(public string $conversationId, public array $acknowledgement, public array $recipientIds = []) {}

    public function broadcastAs(): string
    {
        return 'messaging.message.acknowledged';
    }

    public function broadcastWith(): array
    {
        return ['acknowledgement' => $this->acknowledgement];
    }
}
