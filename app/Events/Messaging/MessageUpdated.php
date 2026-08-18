<?php

namespace App\Events\Messaging;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MessageUpdated extends MessagingBroadcastEvent implements ShouldBroadcast
{
    public function __construct(public string $conversationId, public array $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('messaging.conversation.'.$this->conversationId)];
    }

    public function broadcastAs(): string
    {
        return 'messaging.message.updated';
    }

    public function broadcastWith(): array
    {
        return ['message' => $this->message];
    }
}
