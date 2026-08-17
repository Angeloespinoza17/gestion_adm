<?php

namespace App\Services\Messaging;

use App\Models\Messaging\Message;
use App\Models\Messaging\MessageRecipient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcknowledgementService
{
    public function __construct(private AuditService $audit) {}

    public function acknowledge(Message $message, User $actor, ?string $comment): MessageRecipient
    {
        return DB::transaction(function () use ($message, $actor, $comment) {
            $recipient = MessageRecipient::query()->where('message_id', $message->id)->where('user_id', $actor->id)->lockForUpdate()->firstOrFail();
            if ($recipient->acknowledged_at) {
                return $recipient;
            }
            abort_unless($recipient->acknowledgement_required && ! $recipient->waived_at, 409, 'Este mensaje no tiene un acuse pendiente.');
            abort_if($message->sender_id === $actor->id, 403);
            abort_if($message->acknowledgement_comment_required && blank($comment), 422, 'El comentario es obligatorio.');
            abort_if($message->acknowledgement_due_at?->isPast() && ! config('messaging.acknowledgements.allow_after_due_date'), 409, 'El plazo de acuse finalizó.');
            $recipient->update(['acknowledged_at' => now(), 'acknowledged_message_version' => $message->current_version, 'acknowledged_content_hash' => $message->content_hash, 'acknowledgement_comment' => $comment, 'acknowledged_by_name_snapshot' => $actor->name]);
            $message->touch();
            $this->audit->record('message_acknowledged', $actor->id, $message->conversation_id, $message->id, $actor->id, ['version' => $message->current_version, 'content_hash' => $message->content_hash, 'method' => 'explicit_web_action']);

            return $recipient->fresh();
        });
    }
}
