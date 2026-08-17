<?php

namespace App\Models\Messaging;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageRecipient extends Model
{
    protected $fillable = ['message_id', 'user_id', 'recipient_display_name_snapshot', 'recipient_reference_snapshot', 'acknowledgement_required', 'delivered_at', 'read_at', 'acknowledged_at', 'acknowledged_message_version', 'acknowledged_content_hash', 'acknowledgement_comment', 'acknowledged_by_name_snapshot', 'waived_at', 'waived_by', 'waiver_reason', 'last_reminded_at', 'reminder_count', 'notification_sent_at'];

    protected $casts = ['acknowledgement_required' => 'boolean', 'delivered_at' => 'datetime', 'read_at' => 'datetime', 'acknowledged_at' => 'datetime', 'waived_at' => 'datetime', 'last_reminded_at' => 'datetime', 'notification_sent_at' => 'datetime'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function acknowledgementStatus(): string
    {
        if (! $this->acknowledgement_required) {
            return 'not_requested';
        }
        if ($this->waived_at) {
            return 'waived';
        }
        if ($this->acknowledged_at) {
            return 'acknowledged';
        }
        if ($this->message?->acknowledgement_due_at?->isPast()) {
            return 'overdue';
        }

        return 'pending';
    }
}
