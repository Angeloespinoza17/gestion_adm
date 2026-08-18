<?php

namespace App\Services\Messaging;

use App\Models\Messaging\Message;

class MessagePresenter
{
    public function present(Message $message, int $viewerId): array
    {
        $message->loadMissing([
            'sender:id,name,profile_photo_path',
            'replyTo:id,public_id,body,sender_display_name_snapshot',
            'attachments',
            'reactions',
            'recipients.user:id,name',
        ]);

        $recipients = $message->recipients;
        $recipient = $recipients->firstWhere('user_id', $viewerId);
        $acknowledgementRecipients = $recipients->where('acknowledgement_required', true);
        $pendingAcknowledgements = $acknowledgementRecipients->whereNull('acknowledged_at')->whereNull('waived_at');
        $isOverdue = $message->acknowledgement_due_at?->isPast() ?? false;
        $acknowledgementSummary = $message->requires_acknowledgement ? [
            'total' => $acknowledgementRecipients->count(),
            'acknowledged' => $acknowledgementRecipients->whereNotNull('acknowledged_at')->count(),
            'pending' => $isOverdue ? 0 : $pendingAcknowledgements->count(),
            'overdue' => $isOverdue ? $pendingAcknowledgements->count() : 0,
            'waived' => $acknowledgementRecipients->whereNotNull('waived_at')->count(),
            'acknowledged_by' => $message->sender_id === $viewerId
                ? $acknowledgementRecipients->whereNotNull('acknowledged_at')->sortByDesc('acknowledged_at')->take(5)->map(fn ($item) => [
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
                'body' => $message->replyTo->body,
                'sender' => $message->replyTo->sender_display_name_snapshot,
            ] : null,
            'requires_acknowledgement' => $message->requires_acknowledgement,
            'acknowledgement_due_at' => $message->acknowledgement_due_at,
            'acknowledgement_comment_required' => $message->acknowledgement_comment_required,
            'acknowledgement_status' => $recipient?->acknowledgementStatus() ?? 'not_requested',
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
            'reactions' => $message->reactions->groupBy('reaction')->map(fn ($items, $reaction) => [
                'reaction' => $reaction,
                'count' => $items->count(),
                'mine' => $items->contains('user_id', $viewerId),
            ])->values(),
        ];
    }
}
