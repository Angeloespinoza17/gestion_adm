<?php

namespace App\Models\Messaging;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageVersion extends Model
{
    public $timestamps = false;

    const UPDATED_AT = null;

    protected $fillable = ['message_id', 'version_number', 'subject', 'body', 'priority', 'content_hash', 'edited_by', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
