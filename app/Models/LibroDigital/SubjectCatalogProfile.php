<?php

namespace App\Models\LibroDigital;

use App\Models\Schedule\ScheduleSubject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectCatalogProfile extends LibroDigitalModel
{
    protected $table = 'lcd_subject_catalog_profiles';

    protected function casts(): array
    {
        return [
            'education_types' => 'array',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(ScheduleSubject::class, 'schedule_subject_id');
    }
}
