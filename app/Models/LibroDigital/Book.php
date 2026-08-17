<?php

namespace App\Models\LibroDigital;

use App\Enums\LibroDigital\BookStatus;
use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\LibroDigital\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_books';

    protected function casts(): array
    {
        return [
            'status' => BookStatus::class,
            'digital_enrollment_verified_at' => 'datetime',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'retention_until' => 'date',
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

    public function regulatoryProfile(): BelongsTo
    {
        return $this->belongsTo(RegulatoryProfile::class);
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function teachingGroups(): HasMany
    {
        return $this->hasMany(TeachingGroup::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function monthlyAttendanceClosures(): HasMany
    {
        return $this->hasMany(MonthlyAttendanceClosure::class);
    }

    public function attendanceReconciliations(): HasMany
    {
        return $this->hasMany(AttendanceReconciliation::class);
    }
}
