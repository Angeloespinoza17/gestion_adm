<?php

namespace App\Models\Messaging;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAttachment extends Model
{
    protected $fillable = ['public_id', 'message_id', 'uploaded_by', 'disk', 'path', 'original_name', 'stored_name', 'mime_type', 'size', 'checksum_sha256', 'metadata'];

    protected $casts = ['metadata' => 'array'];

    protected $hidden = ['disk', 'path', 'stored_name'];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
