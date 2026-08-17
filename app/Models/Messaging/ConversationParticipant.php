<?php

namespace App\Models\Messaging;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationParticipant extends Model
{
    protected $fillable = ['conversation_id', 'user_id', 'role', 'can_write', 'joined_at', 'left_at', 'last_read_message_id', 'last_read_at', 'muted_until', 'archived_at', 'pinned_at', 'notification_level'];

    protected $casts = ['can_write' => 'boolean', 'joined_at' => 'datetime', 'left_at' => 'datetime', 'last_read_at' => 'datetime', 'muted_until' => 'datetime', 'archived_at' => 'datetime', 'pinned_at' => 'datetime'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
