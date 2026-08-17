<?php

namespace App\Models\Messaging;

use Illuminate\Database\Eloquent\Model;

class MessagingAuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['public_id', 'conversation_id', 'message_id', 'actor_id', 'target_user_id', 'event_type', 'metadata', 'occurred_at'];

    protected $casts = ['metadata' => 'array', 'occurred_at' => 'datetime'];
}
