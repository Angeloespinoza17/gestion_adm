<?php

namespace App\Models\LibroDigital;

use App\Models\Schedule\ScheduleSubject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectExternalAlias extends LibroDigitalModel
{
    protected $table = 'lcd_subject_external_aliases';

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'confirmed_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(ScheduleSubject::class, 'schedule_subject_id');
    }
}
