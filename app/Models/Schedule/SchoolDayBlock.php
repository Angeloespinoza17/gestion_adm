<?php

namespace App\Models\Schedule;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolDayBlock extends Model
{
    use HasFactory;

    public const TYPE_PEDAGOGICAL = 'pedagogical_block';
    public const TYPE_RECESS = 'recess';
    public const TYPE_LUNCH = 'lunch';
    public const TYPE_NON_ASSIGNABLE = 'non_assignable';

    protected $fillable = [
        'school_day_template_id',
        'day_of_week',
        'start_time',
        'end_time',
        'type',
        'label',
        'order',
        'assignable',
        'pedagogical_hours_equivalent',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'order' => 'integer',
        'assignable' => 'boolean',
        'pedagogical_hours_equivalent' => 'decimal:2',
    ];

    public function schoolDayTemplate(): BelongsTo
    {
        return $this->belongsTo(SchoolDayTemplate::class);
    }
}
