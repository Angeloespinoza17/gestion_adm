<?php

namespace App\Models\Attendance;

use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyAttendanceImport extends Model
{
    protected $fillable = [
        'academic_year_id', 'school_year', 'month', 'version', 'is_active', 'status', 'source',
        'original_filename', 'stored_path', 'mime_type', 'size_bytes', 'checksum', 'sheet_count',
        'parsed_rows', 'matched_rows', 'unmatched_rows', 'imported_records',
        'preserved_manual_records', 'metadata', 'superseded_by_id', 'completed_at',
        'superseded_at', 'created_by',
    ];

    protected $casts = [
        'school_year' => 'integer',
        'month' => 'integer',
        'version' => 'integer',
        'is_active' => 'boolean',
        'size_bytes' => 'integer',
        'sheet_count' => 'integer',
        'parsed_rows' => 'integer',
        'matched_rows' => 'integer',
        'unmatched_rows' => 'integer',
        'imported_records' => 'integer',
        'preserved_manual_records' => 'integer',
        'metadata' => 'array',
        'completed_at' => 'datetime',
        'superseded_at' => 'datetime',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(MonthlyAttendanceImportRow::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
