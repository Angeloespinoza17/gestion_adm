<?php

namespace App\Models\LibroDigital;

use App\Models\AcademicYear;
use App\Models\LibroDigital\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LateArrivalPeriod extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_late_arrival_periods';

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'policy_snapshot' => 'array', 'revision' => 'integer'];
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

    public function teachingGroup(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class);
    }

    public function arrivals(): HasMany
    {
        return $this->hasMany(LateArrival::class);
    }
}
