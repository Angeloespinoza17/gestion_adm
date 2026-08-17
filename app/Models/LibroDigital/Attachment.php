<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_attachments';

    protected function casts(): array
    {
        return [
            'encrypted' => 'boolean',
            'encryption_metadata' => 'array',
            'malware_scanned_at' => 'datetime',
            'retention_until' => 'date',
            'uploaded_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
