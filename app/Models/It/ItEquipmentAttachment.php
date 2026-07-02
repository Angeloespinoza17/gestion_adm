<?php

namespace App\Models\It;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class ItEquipmentAttachment extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const CATEGORY_OPTIONS = [
        'foto',
        'documento',
        'acta',
        'evidencia',
        'informe_pdf',
        'cotizacion',
        'factura',
        'respaldo',
        'otra',
    ];

    protected $table = 'it_equipment_attachments';

    protected $appends = [
        'file_url',
    ];

    protected $fillable = [
        'it_equipment_id',
        'attachable_type',
        'attachable_id',
        'category',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'notes',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'deleted_at' => 'datetime',
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

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(ItEquipment::class, 'it_equipment_id')->withTrashed();
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
