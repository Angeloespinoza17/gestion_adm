<?php

namespace App\Models\Inspectoria;

use App\Models\CourseSection;
use App\Models\Staff;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectoriaPass extends Model
{
    public const STATUSES = ['emitido', 'utilizado', 'vencido', 'anulado'];

    public const PRIORITY = 100;

    public const DESTINATIONS = [
        'enfermeria' => 'Enfermería',
        'convivencia' => 'Convivencia Escolar',
        'direccion' => 'Dirección',
        'utp' => 'UTP',
        'porteria' => 'Portería',
        'biblioteca' => 'Biblioteca',
        'bano' => 'Baño',
        'sala' => 'Otra sala',
        'otro' => 'Otro lugar',
    ];

    protected $table = 'inspectoria_passes';

    protected $fillable = [
        'pass_code', 'student_profile_id', 'course_section_id', 'inspector_staff_id',
        'student_name_snapshot', 'student_rut_snapshot', 'inspector_name_snapshot',
        'destination', 'destination_detail', 'issued_at', 'valid_from', 'valid_until',
        'status', 'priority', 'regulation_version', 'reason', 'signature_data',
        'signature_name', 'signature_rut', 'signed_at', 'used_at', 'notes',
        'issued_by_user_id', 'used_by_user_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'issued_at' => 'datetime', 'valid_from' => 'datetime', 'valid_until' => 'datetime',
        'signed_at' => 'datetime', 'used_at' => 'datetime', 'priority' => 'integer',
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

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by_user_id');
    }
}
