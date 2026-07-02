<?php

namespace App\Models\CentroApuntes;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CentroApuntesAdjunto extends Model
{
    use HasFactory;

    protected $table = 'centro_apuntes_adjuntos';

    protected $appends = [
        'file_url',
    ];

    protected $fillable = [
        'solicitud_id',
        'original_name',
        'file_path',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

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

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(CentroApuntesSolicitud::class, 'solicitud_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
