<?php

namespace App\Models\LibroDigital;

use App\Models\AcademicYear;
use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumImportBatch extends LibroDigitalModel
{
    use HasPublicUlid;

    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_VALIDATING = 'validating';

    public const STATUS_INVALID = 'invalid';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_ACTIVATED = 'activated';

    public const STATUSES = [
        self::STATUS_UPLOADED,
        self::STATUS_VALIDATING,
        self::STATUS_INVALID,
        self::STATUS_VALIDATED,
        self::STATUS_APPROVED,
        self::STATUS_ACTIVATED,
    ];

    protected $table = 'lcd_curriculum_import_batches';

    protected $attributes = [
        'status' => self::STATUS_UPLOADED,
        'lock_version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'storage_metadata' => 'array',
            'manifest' => 'array',
            'validated_payload_encrypted' => 'encrypted:array',
            'validation_errors' => 'array',
            'metadata' => 'array',
            'size_bytes' => 'integer',
            'lock_version' => 'integer',
            'total_row_count' => 'integer',
            'catalog_row_count' => 'integer',
            'objective_row_count' => 'integer',
            'link_row_count' => 'integer',
            'reference_row_count' => 'integer',
            'valid_row_count' => 'integer',
            'invalid_row_count' => 'integer',
            'warning_count' => 'integer',
            'error_count' => 'integer',
            'imported_row_count' => 'integer',
            'skipped_row_count' => 'integer',
            'objective_count' => 'integer',
            'oa_count' => 'integer',
            'oat_count' => 'integer',
            'subject_link_count' => 'integer',
            'requested_at' => 'datetime',
            'validated_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function curriculumCatalog(): BelongsTo
    {
        return $this->belongsTo(CurriculumCatalog::class);
    }

    public function normativeSource(): BelongsTo
    {
        return $this->belongsTo(NormativeSource::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(CurriculumImportEvidence::class, 'import_batch_id');
    }

    public function activations(): HasMany
    {
        return $this->hasMany(CurriculumCatalogActivation::class, 'import_batch_id');
    }
}
