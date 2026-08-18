<?php

namespace App\Events\Messaging;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MessageAcknowledged extends MessagingBroadcastEvent implements ShouldBroadcast
{
    public function __construct(public string $conversationId, public array $acknowledgement) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('messaging.conversation.'.$this->conversationId)];
    }

    public function broadcastAs(): string
    {
        return 'messaging.message.acknowledged';
    }

    public function broadcastWith(): array
    {
        return ['acknowledgement' => $this->acknowledgement];
    }
}
