<?php

namespace App\Models\Library;

use App\Models\CourseSection;
use App\Models\Staff;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BibliotecaPase extends Model
{
    public const STATUSES = ['emitido', 'utilizado', 'vencido', 'anulado'];

    protected $table = 'biblioteca_pases';

    protected $fillable = [
        'pass_code',
        'student_profile_id',
        'course_section_id',
        'professor_staff_id',
        'student_name_snapshot',
        'student_rut_snapshot',
        'professor_name_snapshot',
        'issued_at',
        'valid_from',
        'valid_until',
        'status',
        'regulation_version',
        'reason',
        'signature_data',
        'signature_name',
        'signature_rut',
        'signed_at',
        'used_at',
        'notes',
        'issued_by_user_id',
        'used_by_user_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'signed_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'professor_staff_id');
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
