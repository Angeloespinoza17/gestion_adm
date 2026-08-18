<?php

namespace App\Events\Messaging;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MessageRead extends MessagingBroadcastEvent implements ShouldBroadcast
{
    public function __construct(public string $conversationId, public array $receipt) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('messaging.conversation.'.$this->conversationId)];
    }

    public function broadcastAs(): string
    {
        return 'messaging.message.read';
    }

    public function broadcastWith(): array
    {
        return ['receipt' => $this->receipt];
    }
}
