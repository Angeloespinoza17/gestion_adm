<?php

namespace App\Services\Messaging;

use App\Events\Messaging\ConversationChanged;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Models\Messaging\MessageRecipient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MessagingBroadcaster
{
    private const RECIPIENT_CHANNEL_BATCH_SIZE = 100;

    private const FULL_REACTION_AUDIENCE_LIMIT = 100;

    public function messageCreated(Message $message, int $actorId): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $payload = $this->messageReference($message);
        $recipientIds = $this->activeRecipientIds($message->conversation);
        $this->broadcastConversationChange($recipientIds, $this->changePayload($message->conversation, 'message_created', [
            'actor_id' => $actorId,
            'unread_delta' => 1,
            'message_reference' => $payload,
            'last_message' => $this->lastMessage($message),
        ]));
    }

    public function messageUpdated(Message $message, int $actorId): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $payload = $this->messageReference($message);
        $recipientIds = $this->activeRecipientIds($message->conversation);
        $this->broadcastConversationChange($recipientIds, $this->changePayload($message->conversation, 'message_updated', [
            'actor_id' => $actorId,
            'message_reference' => $payload,
            'last_message' => $message->conversation->last_message_id === $message->id ? $this->lastMessage($message) : null,
        ]));
    }

    public function messageDeleted(Conversation $conversation, string $messageId, int $actorId): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $recipientIds = $this->activeRecipientIds($conversation);
        $conversation->loadMissing('lastMessage');
        $this->broadcastConversationChange($recipientIds, $this->changePayload($conversation, 'message_deleted', [
            'actor_id' => $actorId,
            'message_id' => $messageId,
            'last_message' => $conversation->lastMessage ? $this->lastMessage($conversation->lastMessage) : null,
            'last_message_at' => $conversation->last_message_at,
        ]));
    }

    public function reactionUpdated(Message $message, int $actorId, string $reaction, bool $active): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $message->loadMissing('conversation');
        $payload = [
            'message_id' => $message->public_id,
            'user_id' => $actorId,
            'reaction' => $reaction,
            'active' => $active,
        ];
        $recipientIds = $this->activeRecipientCount($message->conversation) <= self::FULL_REACTION_AUDIENCE_LIMIT
            ? $this->activeRecipientIds($message->conversation)
            : $this->activeConversationUserIds($message->conversation, [$actorId, $message->sender_id]);
        $this->broadcastConversationChange($recipientIds, $this->changePayload($message->conversation, 'message_reaction_updated', [
            'reaction' => $payload,
        ]));
    }

    public function messageRead(Conversation $conversation, int $readerId, string $throughMessageId, int $count, ?int $senderId = null): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $receipt = [
            'conversation_id' => $conversation->public_id,
            'reader_id' => $readerId,
            'through_message_id' => $throughMessageId,
            'read_at' => now()->toIso8601String(),
            'updated' => $count,
        ];
        $recipientIds = $this->activeConversationUserIds($conversation, [$readerId, $senderId]);
        $this->broadcastConversationChange($recipientIds, $this->changePayload($conversation, 'messages_read', [
            'actor_id' => $readerId,
            'reader_id' => $readerId,
            'unread_count' => 0,
            'read_count' => $count,
            'receipt' => $receipt,
        ]));
    }

    public function messageAcknowledged(Message $message, MessageRecipient $recipient): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $payload = [
            'message_id' => $message->public_id,
            'user_id' => $recipient->user_id,
            'acknowledged_at' => $recipient->acknowledged_at,
        ];
        $recipientIds = $this->activeConversationUserIds($message->conversation, [$recipient->user_id, $message->sender_id]);
        $this->broadcastConversationChange($recipientIds, $this->changePayload($message->conversation, 'message_acknowledged', [
            'actor_id' => $recipient->user_id,
            'message_id' => $message->public_id,
            'acknowledgement' => $payload,
        ]));
    }

    public function conversationChanged(Conversation $conversation, string $action, array $extra = [], ?array $recipientIds = null): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $recipientIds = $recipientIds === null
            ? $this->activeRecipientIds($conversation)
            : $this->activeUserIds($recipientIds);
        $this->broadcastConversationChange($recipientIds, $this->changePayload($conversation, $action, $extra));
    }

    private function activeRecipientIds(Conversation $conversation): array
    {
        return DB::table('conversation_participants as cp')
            ->join('users as u', 'u.id', '=', 'cp.user_id')
            ->where('cp.conversation_id', $conversation->id)
            ->whereNull('cp.left_at')
            ->whereIn('u.id', User::query()->messagingStaff()->select('id'))
            ->orderBy('cp.user_id')
            ->pluck('cp.user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function activeRecipientCount(Conversation $conversation): int
    {
        return DB::table('conversation_participants as cp')
            ->join('users as u', 'u.id', '=', 'cp.user_id')
            ->where('cp.conversation_id', $conversation->id)
            ->whereNull('cp.left_at')
            ->whereIn('u.id', User::query()->messagingStaff()->select('id'))
            ->count();
    }

    private function activeConversationUserIds(Conversation $conversation, array $recipientIds): array
    {
        $recipientIds = collect($recipientIds)
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        if ($recipientIds === []) {
            return [];
        }

        return DB::table('conversation_participants as cp')
            ->join('users as u', 'u.id', '=', 'cp.user_id')
            ->where('cp.conversation_id', $conversation->id)
            ->whereNull('cp.left_at')
            ->whereIn('u.id', User::query()->messagingStaff()->select('id'))
            ->whereIn('cp.user_id', $recipientIds)
            ->orderBy('cp.user_id')
            ->pluck('cp.user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function activeUserIds(array $recipientIds): array
    {
        $recipientIds = collect($recipientIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();
        $activeIds = collect();

        foreach ($recipientIds->chunk(500) as $idChunk) {
            $activeIds->push(...User::query()
                ->messagingStaff()
                ->whereIn('id', $idChunk->all())
                ->pluck('id')
                ->map(fn ($id) => (int) $id));
        }

        return $activeIds->unique()->values()->all();
    }

    private function changePayload(Conversation $conversation, string $action, array $extra): array
    {
        return array_merge([
            'action' => $action,
            'conversation_id' => $conversation->public_id,
            'occurred_at' => now()->toIso8601String(),
        ], $extra);
    }

    private function broadcastConversationChange(array $recipientIds, array $change): void
    {
        $this->broadcastInBatches($recipientIds, fn (array $batch) => new ConversationChanged($batch, $change));
    }

    private function broadcastInBatches(array $recipientIds, callable $event): void
    {
        foreach (array_chunk($recipientIds, self::RECIPIENT_CHANNEL_BATCH_SIZE) as $recipientBatch) {
            broadcast($event($recipientBatch))->toOthers();
        }
    }

    private function lastMessage(Message $message): array
    {
        return [
            'public_id' => $message->public_id,
            'body' => mb_strimwidth((string) $message->body, 0, 240, '…'),
            'subject' => $message->subject,
            'sender' => $message->sender_display_name_snapshot,
            'priority' => $message->priority,
            'sent_at' => $message->sent_at,
        ];
    }

    private function messageReference(Message $message): array
    {
        $message->loadMissing('conversation:id,public_id');

        return [
            'public_id' => $message->public_id,
            'conversation_id' => $message->conversation->public_id,
            'sent_at' => $message->sent_at,
            'edited_at' => $message->edited_at,
            'version' => (int) $message->current_version,
        ];
    }

    public function isEnabled(): bool
    {
        if (! config('messaging.enabled')
            || ! config('messaging.realtime.enabled')
            || config('broadcasting.default') !== 'reverb'
            || config('broadcasting.connections.reverb.driver') !== 'reverb'
            || config('queue.default') === 'sync') {
            return false;
        }

        foreach (['key', 'app_id', 'secret'] as $credential) {
            if (trim((string) config('broadcasting.connections.reverb.'.$credential)) === '') {
                return false;
            }
        }

        return true;
    }
}
