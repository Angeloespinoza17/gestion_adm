<?php

namespace App\Models\Convivencia;

use App\Models\CourseSection;
use App\Models\Staff;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConvivenciaCasePerson extends Model
{
    use HasFactory;

    protected $table = 'convivencia_case_people';

    protected $fillable = [
        'case_id',
        'student_profile_id',
        'user_id',
        'staff_id',
        'course_section_id',
        'person_type',
        'role_type',
        'full_name',
        'identifier',
        'relationship_label',
        'contact_reference',
        'notes',
        'is_sensitive',
    ];

    protected $casts = [
        'is_sensitive' => 'boolean',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaCase::class, 'case_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }
}
