<?php

namespace App\Models\PedagogicalManagement;

use App\Models\PedagogicalManagement\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class PedagogicalInstrumentFile extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected static function booted(): void
    {
        static::updating(function (self $file): void {
            $immutable = ['school_id', 'instrument_id', 'version', 'original_filename', 'internal_filename', 'storage_disk', 'storage_path', 'mime_type', 'file_size', 'sha256', 'uploaded_by'];
            if (collect($immutable)->contains(fn (string $attribute): bool => $file->isDirty($attribute))) {
                throw new LogicException('La versión original del archivo es inmutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Los archivos originales no se eliminan; se archiva el instrumento.'));
    }

    protected function casts(): array
    {
        return [
            'file_size' => 'integer', 'page_count' => 'integer', 'is_encrypted' => 'boolean',
            'has_text_layer' => 'boolean', 'technical_metadata' => 'array',
        ];
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(PedagogicalInstrument::class, 'instrument_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function analysisRuns(): HasMany
    {
        return $this->hasMany(PedagogicalInstrumentAnalysisRun::class, 'instrument_file_id')->latest('id');
    }

    public function aiReports(): HasMany
    {
        return $this->hasMany(PedagogicalInstrumentAiReport::class, 'instrument_file_id')->latest('id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PedagogicalInstrumentReview::class, 'instrument_file_id')->latest('reviewed_at');
    }
}
