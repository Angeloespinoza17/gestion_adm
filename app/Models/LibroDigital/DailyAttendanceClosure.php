<?php

namespace App\Models\LibroDigital;

use App\Enums\LibroDigital\ClosureStatus;
use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyAttendanceClosure extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_daily_attendance_closures';

    protected function casts(): array
    {
        return [
            'status' => ClosureStatus::class,
            'closure_date' => 'date',
            'anomalies' => 'array',
            'snapshot' => 'array',
            'closed_at' => 'datetime',
        ];
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

    public function rosterSnapshot(): BelongsTo
    {
        return $this->belongsTo(RosterSnapshot::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
