<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsenceCaseAction extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_absence_case_actions';

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'evidence' => 'array',
            'next_deadline_on' => 'date',
        ];
    }

    public function absenceCase(): BelongsTo
    {
        return $this->belongsTo(AbsenceCase::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
