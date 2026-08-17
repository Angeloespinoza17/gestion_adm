<?php

namespace App\Models\LibroDigital;

use App\Enums\LibroDigital\EdeExportStatus;
use App\Models\AcademicYear;
use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EdeExport extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_ede_exports';

    protected $attributes = [
        'lock_version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'status' => EdeExportStatus::class,
            'lock_version' => 'integer',
            'scope_snapshot' => 'array',
            'manifest' => 'array',
            'requested_at' => 'datetime',
            'generated_at' => 'datetime',
            'validated_at' => 'datetime',
            'released_at' => 'datetime',
            'stale_at' => 'datetime',
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

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function regulatoryProfile(): BelongsTo
    {
        return $this->belongsTo(RegulatoryProfile::class);
    }

    public function edeVersion(): BelongsTo
    {
        return $this->belongsTo(EdeVersion::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(EdeExportFile::class);
    }

    public function validationRuns(): HasMany
    {
        return $this->hasMany(EdeValidationRun::class);
    }

    public function validationResults(): HasMany
    {
        return $this->hasMany(EdeValidationResult::class);
    }
}
