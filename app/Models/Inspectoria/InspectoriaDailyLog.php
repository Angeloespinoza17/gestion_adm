<?php

namespace App\Models\Inspectoria;

use App\Models\CourseSection;
use App\Models\Staff;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectoriaDailyLog extends Model
{
    public const CATEGORIES = [
        'novedad' => 'Novedad',
        'convivencia' => 'Convivencia',
        'infraestructura' => 'Infraestructura',
        'salud' => 'Salud',
        'asistencia' => 'Asistencia / puntualidad',
        'apoderado' => 'Apoderado',
        'seguridad' => 'Seguridad',
        'observacion_positiva' => 'Observación positiva',
        'otro' => 'Otro',
    ];

    protected $table = 'inspectoria_daily_logs';

    protected $fillable = [
        'student_profile_id', 'course_section_id', 'inspector_staff_id', 'registered_by_user_id',
        'happened_at', 'category', 'priority', 'status', 'title', 'detail',
        'requires_follow_up', 'follow_up_note', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'happened_at' => 'datetime:Y-m-d H:i:s',
        'requires_follow_up' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'inspector_staff_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }
}
