<?php

namespace App\Models\Attendance;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceImport extends Model
{
    protected $fillable = [
        'academic_year_id', 'course_section_id', 'source', 'status', 'conflict_strategy',
        'original_filename', 'stored_path', 'mime_type', 'size_bytes', 'checksum',
        'parsed_students', 'matched_students', 'unmatched_students', 'imported_records',
        'conflict_records', 'preview_payload', 'validation_payload', 'failure_message',
        'confirmed_at', 'created_by', 'confirmed_by',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'parsed_students' => 'integer',
        'matched_students' => 'integer',
        'unmatched_students' => 'integer',
        'imported_records' => 'integer',
        'conflict_records' => 'integer',
        'preview_payload' => 'array',
        'validation_payload' => 'array',
        'confirmed_at' => 'datetime',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
