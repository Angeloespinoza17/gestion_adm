<?php

namespace App\Models\LibroDigital;

use App\Enums\LibroDigital\ClosureStatus;
use App\Models\AcademicYear;
use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyAttendanceClosure extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_monthly_attendance_closures';

    protected function casts(): array
    {
        return ['status' => ClosureStatus::class, 'snapshot' => 'array', 'attendance_percentage' => 'decimal:3', 'closed_at' => 'datetime'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function teachingGroup(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
