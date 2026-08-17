<?php

namespace App\Services\Messaging;

use App\Models\Messaging\MessagingAuditEvent;
use Illuminate\Support\Str;

class AuditService
{
    public function record(string $type, ?int $actorId = null, ?int $conversationId = null, ?int $messageId = null, ?int $targetUserId = null, array $metadata = []): void
    {
        MessagingAuditEvent::query()->create(['public_id' => (string) Str::ulid(), 'conversation_id' => $conversationId, 'message_id' => $messageId, 'actor_id' => $actorId, 'target_user_id' => $targetUserId, 'event_type' => $type, 'metadata' => $metadata ?: null, 'occurred_at' => now()]);
    }
}
