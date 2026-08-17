<?php

namespace App\Models\LibroDigital;

use App\Models\AcademicYear;
use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceReconciliation extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_attendance_reconciliations';

    protected function casts(): array
    {
        return ['discrepancies' => 'array', 'resolution' => 'array', 'resolved_at' => 'datetime'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
