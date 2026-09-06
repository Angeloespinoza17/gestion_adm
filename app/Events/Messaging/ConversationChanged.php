<?php

namespace App\Events\Messaging;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ConversationChanged extends MessagingBroadcastEvent implements ShouldBroadcast
{
    use BroadcastsToMessagingUsers;

    public function __construct(public array $recipientIds, public array $change) {}

    public function broadcastAs(): string
    {
        return 'messaging.conversation.changed';
    }

    public function broadcastWith(): array
    {
        return ['change' => $this->change];
    }
}
