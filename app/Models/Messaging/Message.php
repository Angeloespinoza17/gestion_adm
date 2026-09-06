<?php

namespace App\Models\Messaging;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use SoftDeletes;

    protected $fillable = ['public_id', 'conversation_id', 'sender_id', 'sender_display_name_snapshot', 'kind', 'subject', 'body', 'body_format', 'priority', 'reply_to_id', 'supersedes_message_id', 'requires_acknowledgement', 'acknowledgement_due_at', 'acknowledgement_comment_required', 'allow_replies', 'dispatch_status', 'recipient_count', 'current_version', 'content_hash', 'sent_at', 'edited_at', 'metadata'];

    protected $casts = ['requires_acknowledgement' => 'boolean', 'acknowledgement_comment_required' => 'boolean', 'allow_replies' => 'boolean', 'acknowledgement_due_at' => 'datetime', 'sent_at' => 'datetime', 'edited_at' => 'datetime', 'metadata' => 'array'];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_message_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MessageRecipient::class);
    }

    public function recentAcknowledgements(): HasMany
    {
        return $this->hasMany(MessageRecipient::class)
            ->where('acknowledgement_required', true)
            ->whereNotNull('acknowledged_at')
            ->orderByDesc('acknowledged_at')
            ->orderByDesc('id')
            ->limit(5);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(MessageMention::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function reactionSummaries(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(MessageVersion::class);
    }
}
