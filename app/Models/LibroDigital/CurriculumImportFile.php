<?php

namespace App\Models\LibroDigital;

use App\Models\AcademicYear;
use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumImportFile extends LibroDigitalModel
{
    use HasPublicUlid;

    public const TERMINAL_STATUSES = ['pending_review', 'validated', 'published', 'published_with_warnings', 'failed', 'archived'];

    protected $table = 'lcd_curriculum_import_files';

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'progress' => 'integer',
            'last_valid_stage_order' => 'integer',
            'attempt_count' => 'integer',
            'detected_metadata' => 'array',
            'warnings' => 'array',
            'cancel_requested_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'processing_completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CurriculumImportBatch::class, 'import_batch_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(CurriculumDocument::class, 'curriculum_document_id');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(CurriculumImportCandidate::class, 'import_file_id');
    }

    public function conflicts(): HasMany
    {
        return $this->hasMany(CurriculumImportConflict::class, 'import_file_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CurriculumImportLog::class, 'import_file_id')->orderBy('id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
