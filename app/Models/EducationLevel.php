<?php

namespace App\Models;

use App\Models\Schedule\SchoolDayTemplate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EducationLevel extends Model
{
    use HasFactory;

    public const TYPE_OPTIONS = [
        ['value' => 'parvularia', 'label' => 'Parvularia'],
        ['value' => 'basica', 'label' => 'Básica'],
        ['value' => 'media', 'label' => 'Media'],
    ];

    protected $fillable = [
        'name',
        'order',
        'type',
        'default_school_day_template_id',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    public function courseSections(): HasMany
    {
        return $this->hasMany(CourseSection::class)->orderBy('section_name');
    }

    public function defaultSchoolDayTemplate(): BelongsTo
    {
        return $this->belongsTo(SchoolDayTemplate::class, 'default_school_day_template_id');
    }
}
