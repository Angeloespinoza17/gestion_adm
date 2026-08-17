<?php

namespace App\Events\Messaging;

use App\Models\Messaging\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('messaging.conversation.'.$this->message->conversation->public_id)];
    }

    public function broadcastAs(): string
    {
        return 'messaging.message.created';
    }

    public function broadcastWith(): array
    {
        return ['message' => [
            'public_id' => $this->message->public_id,
            'conversation_id' => $this->message->conversation->public_id,
            'sender_id' => $this->message->sender_id,
            'sender' => ['name' => $this->message->sender_display_name_snapshot],
            'kind' => $this->message->kind,
            'body' => $this->message->body,
            'subject' => $this->message->subject,
            'priority' => $this->message->priority,
            'requires_acknowledgement' => $this->message->requires_acknowledgement,
            'sent_at' => $this->message->sent_at?->toIso8601String(),
            'attachments' => [],
            'reactions' => [],
        ]];
    }
}
