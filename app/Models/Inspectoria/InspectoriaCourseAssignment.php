<?php

namespace App\Models\Inspectoria;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectoriaCourseAssignment extends Model
{
    protected $table = 'inspectoria_course_assignments';

    protected $fillable = [
        'academic_year_id', 'course_section_id', 'inspector_staff_id', 'physical_location',
        'starts_on', 'ends_on', 'active', 'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'starts_on' => 'date:Y-m-d',
        'ends_on' => 'date:Y-m-d',
        'active' => 'boolean',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'inspector_staff_id');
    }
}
