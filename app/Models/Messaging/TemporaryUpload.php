<?php

namespace App\Models\Messaging;

use Illuminate\Database\Eloquent\Model;

class TemporaryUpload extends Model
{
    protected $table = 'messaging_temporary_uploads';

    protected $fillable = ['public_id', 'user_id', 'disk', 'path', 'original_name', 'stored_name', 'mime_type', 'size', 'checksum_sha256', 'expires_at', 'consumed_at'];

    protected $casts = ['expires_at' => 'datetime', 'consumed_at' => 'datetime'];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
