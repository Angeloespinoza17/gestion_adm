<?php

namespace App\Services\Messaging;

use App\Models\Messaging\Message;
use App\Models\Messaging\MessageRecipient;
use Illuminate\Database\Eloquent\Builder;

class MessagePresenter
{
    public function prepareQuery(Builder $query, int $viewerId): Builder
    {
        return $query
            ->with([
                'conversation:id,public_id',
                'sender:id,name,profile_photo_path',
                'replyTo' => fn ($replyQuery) => $replyQuery
                    ->select(['id', 'public_id', 'sender_display_name_snapshot'])
                    ->selectRaw('SUBSTR(body, 1, 240) AS body'),
                'attachments',
                'recipients' => fn ($recipientQuery) => $recipientQuery->where('user_id', $viewerId),
                'recentAcknowledgements.user:id,name',
                'reactionSummaries' => fn ($reactionQuery) => $reactionQuery
                    ->selectRaw(
                        'message_id, reaction, COUNT(*) AS reaction_count, MAX(CASE WHEN user_id = ? THEN 1 ELSE 0 END) AS viewer_reacted',
                        [$viewerId]
                    )
                    ->groupBy('message_id', 'reaction'),
            ])
            ->withCount([
                'recipients as acknowledgement_total_count' => fn ($recipientQuery) => $recipientQuery->where('acknowledgement_required', true),
                'recipients as acknowledgement_acknowledged_count' => fn ($recipientQuery) => $recipientQuery->where('acknowledgement_required', true)->whereNotNull('acknowledged_at'),
                'recipients as acknowledgement_outstanding_count' => fn ($recipientQuery) => $recipientQuery->where('acknowledgement_required', true)->whereNull('acknowledged_at')->whereNull('waived_at'),
                'recipients as acknowledgement_waived_count' => fn ($recipientQuery) => $recipientQuery->where('acknowledgement_required', true)->whereNotNull('waived_at'),
            ]);
    }

    public function present(Message $message, int $viewerId): array
    {
        if (! $this->isPrepared($message)) {
            $message = $this->prepareQuery(Message::query()->whereKey($message->getKey()), $viewerId)->firstOrFail();
        }

        $recipient = $message->recipients->firstWhere('user_id', $viewerId);
        $outstandingAcknowledgements = (int) $message->acknowledgement_outstanding_count;
        $isOverdue = $message->acknowledgement_due_at?->isPast() ?? false;
        $acknowledgementSummary = $message->requires_acknowledgement ? [
            'total' => (int) $message->acknowledgement_total_count,
            'acknowledged' => (int) $message->acknowledgement_acknowledged_count,
            'pending' => $isOverdue ? 0 : $outstandingAcknowledgements,
            'overdue' => $isOverdue ? $outstandingAcknowledgements : 0,
            'waived' => (int) $message->acknowledgement_waived_count,
            'acknowledged_by' => $message->sender_id === $viewerId
                ? $message->recentAcknowledgements->map(fn ($item) => [
                    'name' => $item->acknowledged_by_name_snapshot ?: $item->user?->name ?: $item->recipient_display_name_snapshot,
                    'acknowledged_at' => $item->acknowledged_at,
                ])->values()
                : [],
        ] : null;

        return [
            'public_id' => $message->public_id,
            'conversation_id' => $message->conversation?->public_id,
            'sender_id' => $message->sender_id,
            'sender' => [
                'name' => $message->sender?->name ?: $message->sender_display_name_snapshot,
                'photo' => $message->sender?->profile_photo_url,
            ],
            'kind' => $message->kind,
            'subject' => $message->subject,
            'body' => $message->body,
            'priority' => $message->priority,
            'reply_to' => $message->replyTo ? [
                'public_id' => $message->replyTo->public_id,
                'body' => mb_strimwidth((string) $message->replyTo->body, 0, 240, '…'),
                'sender' => $message->replyTo->sender_display_name_snapshot,
            ] : null,
            'requires_acknowledgement' => $message->requires_acknowledgement,
            'acknowledgement_due_at' => $message->acknowledgement_due_at,
            'acknowledgement_comment_required' => $message->acknowledgement_comment_required,
            'acknowledgement_status' => $this->acknowledgementStatus($message, $recipient),
            'acknowledgement_summary' => $acknowledgementSummary,
            'content_hash' => $message->content_hash,
            'sent_at' => $message->sent_at,
            'edited_at' => $message->edited_at,
            'attachments' => $message->attachments->map(fn ($attachment) => [
                'public_id' => $attachment->public_id,
                'name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'download_url' => '/api/messaging/attachments/'.$attachment->public_id,
            ])->values(),
            'reactions' => $message->reactionSummaries->map(fn ($summary) => [
                'reaction' => $summary->reaction,
                'count' => (int) $summary->reaction_count,
                'mine' => (bool) $summary->viewer_reacted,
            ])->values(),
        ];
    }

    private function isPrepared(Message $message): bool
    {
        return $message->relationLoaded('conversation')
            && $message->relationLoaded('sender')
            && $message->relationLoaded('replyTo')
            && $message->relationLoaded('attachments')
            && $message->relationLoaded('reactionSummaries')
            && $message->relationLoaded('recipients')
            && $message->relationLoaded('recentAcknowledgements')
            && array_key_exists('acknowledgement_total_count', $message->getAttributes())
            && array_key_exists('acknowledgement_acknowledged_count', $message->getAttributes())
            && array_key_exists('acknowledgement_outstanding_count', $message->getAttributes())
            && array_key_exists('acknowledgement_waived_count', $message->getAttributes());
    }

    private function acknowledgementStatus(Message $message, ?MessageRecipient $recipient): string
    {
        if (! $recipient?->acknowledgement_required) {
            return 'not_requested';
        }
        if ($recipient->waived_at) {
            return 'waived';
        }
        if ($recipient->acknowledged_at) {
            return 'acknowledged';
        }

        return $message->acknowledgement_due_at?->isPast() ? 'overdue' : 'pending';
    }
}
