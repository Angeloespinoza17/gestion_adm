<?php

namespace App\Services\Messaging;

use App\Events\Messaging\ConversationChanged;
use App\Events\Messaging\MessageAcknowledged;
use App\Events\Messaging\MessageCreated;
use App\Events\Messaging\MessageDeleted;
use App\Events\Messaging\MessageReactionUpdated;
use App\Events\Messaging\MessageRead;
use App\Events\Messaging\MessageUpdated;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Models\Messaging\MessageRecipient;

class MessagingBroadcaster
{
    public function __construct(private MessagePresenter $messages) {}

    public function messageCreated(Message $message, int $actorId): void
    {
        if (! $this->enabled()) {
            return;
        }

        $message->loadMissing(['conversation', 'sender:id,name,profile_photo_path', 'replyTo:id,public_id,body,sender_display_name_snapshot', 'attachments', 'reactions', 'recipients.user:id,name']);
        $payload = $this->messages->present($message, 0);
        broadcast(new MessageCreated($message->conversation->public_id, $payload))->toOthers();

        $this->conversationChanged($message->conversation, 'message_created', [
            'actor_id' => $actorId,
            'unread_delta' => 1,
            'last_message' => $this->lastMessage($message),
        ]);
    }

    public function messageUpdated(Message $message, int $actorId): void
    {
        if (! $this->enabled()) {
            return;
        }

        $message->loadMissing(['conversation', 'sender:id,name,profile_photo_path', 'replyTo:id,public_id,body,sender_display_name_snapshot', 'attachments', 'reactions', 'recipients.user:id,name']);
        broadcast(new MessageUpdated($message->conversation->public_id, $this->messages->present($message, 0)))->toOthers();
        $this->conversationChanged($message->conversation, 'message_updated', [
            'actor_id' => $actorId,
            'last_message' => $message->conversation->last_message_id === $message->id ? $this->lastMessage($message) : null,
        ]);
    }

    public function messageDeleted(Conversation $conversation, string $messageId, int $actorId): void
    {
        if (! $this->enabled()) {
            return;
        }

        broadcast(new MessageDeleted($conversation->public_id, $messageId, now()->toIso8601String()))->toOthers();
        $conversation->loadMissing('lastMessage.sender:id,name');
        $this->conversationChanged($conversation, 'message_deleted', [
            'actor_id' => $actorId,
            'message_id' => $messageId,
            'last_message' => $conversation->lastMessage ? $this->lastMessage($conversation->lastMessage) : null,
            'last_message_at' => $conversation->last_message_at,
        ]);
    }

    public function reactionUpdated(Message $message, int $actorId, string $reaction, bool $active): void
    {
        if (! $this->enabled()) {
            return;
        }

        $message->loadMissing('conversation');
        broadcast(new MessageReactionUpdated($message->conversation->public_id, [
            'message_id' => $message->public_id,
            'user_id' => $actorId,
            'reaction' => $reaction,
            'active' => $active,
        ]))->toOthers();
    }

    public function messageRead(Conversation $conversation, int $readerId, string $throughMessageId, int $count): void
    {
        if (! $this->enabled()) {
            return;
        }

        $receipt = [
            'conversation_id' => $conversation->public_id,
            'reader_id' => $readerId,
            'through_message_id' => $throughMessageId,
            'read_at' => now()->toIso8601String(),
            'updated' => $count,
        ];
        broadcast(new MessageRead($conversation->public_id, $receipt))->toOthers();
        $this->conversationChanged($conversation, 'messages_read', [
            'actor_id' => $readerId,
            'reader_id' => $readerId,
            'unread_count' => 0,
            'read_count' => $count,
        ], [$readerId]);
    }

    public function messageAcknowledged(Message $message, MessageRecipient $recipient): void
    {
        if (! $this->enabled()) {
            return;
        }

        $message->loadMissing(['conversation', 'sender:id,name,profile_photo_path', 'replyTo:id,public_id,body,sender_display_name_snapshot', 'attachments', 'reactions', 'recipients.user:id,name']);
        $presented = $this->messages->present($message, $message->sender_id);
        $payload = [
            'message_id' => $message->public_id,
            'user_id' => $recipient->user_id,
            'name' => $recipient->acknowledged_by_name_snapshot ?: $recipient->recipient_display_name_snapshot,
            'acknowledged_at' => $recipient->acknowledged_at,
            'summary' => $presented['acknowledgement_summary'],
        ];
        broadcast(new MessageAcknowledged($message->conversation->public_id, $payload))->toOthers();
        $this->conversationChanged($message->conversation, 'message_acknowledged', [
            'actor_id' => $recipient->user_id,
            'message_id' => $message->public_id,
        ], [$recipient->user_id, $message->sender_id]);
    }

    public function conversationChanged(Conversation $conversation, string $action, array $extra = [], ?array $recipientIds = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        $recipientIds ??= $conversation->activeParticipants()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        if ($recipientIds === []) {
            return;
        }

        broadcast(new ConversationChanged($recipientIds, array_merge([
            'action' => $action,
            'conversation_id' => $conversation->public_id,
            'occurred_at' => now()->toIso8601String(),
        ], $extra)))->toOthers();
    }

    private function lastMessage(Message $message): array
    {
        return [
            'public_id' => $message->public_id,
            'body' => $message->body,
            'subject' => $message->subject,
            'sender' => $message->sender?->name ?: $message->sender_display_name_snapshot,
            'priority' => $message->priority,
            'sent_at' => $message->sent_at,
        ];
    }

    private function enabled(): bool
    {
        return (bool) config('messaging.realtime.enabled');
    }
}
