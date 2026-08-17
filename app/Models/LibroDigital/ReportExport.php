<?php

namespace App\Models\LibroDigital;

use App\Enums\LibroDigital\ReportExportStatus;
use App\Models\AcademicYear;
use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_report_exports';

    protected function casts(): array
    {
        return [
            'status' => ReportExportStatus::class,
            'filters_snapshot' => 'array',
            'draft_watermark' => 'boolean',
            'requested_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
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

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
