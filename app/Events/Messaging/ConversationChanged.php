<?php

namespace App\Events\Messaging;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ConversationChanged extends MessagingBroadcastEvent implements ShouldBroadcast
{
    public function __construct(public array $recipientIds, public array $change) {}

    public function broadcastOn(): array
    {
        return collect($this->recipientIds)
            ->unique()
            ->map(fn (int $id) => new PrivateChannel('messaging.user.'.$id))
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'messaging.conversation.changed';
    }

    public function broadcastWith(): array
    {
        return ['change' => $this->change];
    }
}
