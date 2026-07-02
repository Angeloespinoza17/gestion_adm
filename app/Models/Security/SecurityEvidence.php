<?php

namespace App\Models\Security;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class SecurityEvidence extends Model
{
    use HasFactory;

    protected $table = 'security_evidences';

    protected $fillable = [
        'uploaded_by_user_id',
        'kind',
        'file_path',
        'caption',
        'taken_at',
        'metadata',
    ];

    protected $casts = [
        'taken_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $appends = [
        'file_url',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function getFileUrlAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }

        $url = Storage::disk('public')->url($this->file_path);
        $parts = parse_url((string) $url);

        if (is_array($parts) && isset($parts['path'])) {
            return $parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }

        return $url;
    }
}
