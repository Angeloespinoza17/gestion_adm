<?php

namespace App\Models\LibroDigital;

use App\Models\AcademicYear;
use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumImportEvidence extends LibroDigitalModel
{
    use HasPublicUlid;

    public const STATUS_PENDING_VERIFICATION = 'pending_verification';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING_VERIFICATION,
        self::STATUS_VERIFIED,
        self::STATUS_REJECTED,
    ];

    protected $table = 'lcd_curriculum_import_evidences';

    protected $attributes = [
        'status' => self::STATUS_PENDING_VERIFICATION,
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'storage_metadata' => 'array',
            'manifest' => 'array',
            'metadata' => 'array',
            'captured_at' => 'datetime',
            'verified_at' => 'datetime',
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

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(CurriculumImportBatch::class, 'import_batch_id');
    }

    public function curriculumCatalog(): BelongsTo
    {
        return $this->belongsTo(CurriculumCatalog::class);
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
