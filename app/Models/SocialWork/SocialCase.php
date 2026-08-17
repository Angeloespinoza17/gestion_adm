<?php

namespace App\Models\SocialWork;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SocialCase extends SocialWorkModel
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'social_work_cases';

    public const STATUSES = ['borrador', 'recibido', 'evaluacion_inicial', 'abierto', 'intervencion', 'espera_antecedentes', 'seguimiento', 'derivado_externamente', 'pendiente_cierre', 'cerrado', 'reabierto', 'anulado'];
    public const RISK_LEVELS = ['sin_evaluar', 'bajo', 'medio', 'alto', 'critico'];
    public const PRIORITIES = ['baja', 'media', 'alta', 'urgente'];
    public const CONFIDENTIALITY = ['interno', 'restringido', 'altamente_restringido'];

    protected $casts = [
        'received_on' => 'date:Y-m-d', 'opened_on' => 'date:Y-m-d', 'due_at' => 'datetime',
        'last_activity_at' => 'datetime', 'closed_at' => 'datetime', 'reopen_count' => 'integer',
    ];

    public function student(): BelongsTo { return $this->belongsTo(StudentProfile::class, 'primary_student_id'); }
    public function students(): BelongsToMany { return $this->belongsToMany(StudentProfile::class, 'social_work_case_students', 'case_id', 'student_profile_id')->withPivot(['relationship', 'is_primary'])->withTimestamps(); }
    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class); }
    public function courseSection(): BelongsTo { return $this->belongsTo(CourseSection::class); }
    public function responsible(): BelongsTo { return $this->belongsTo(User::class, 'responsible_user_id'); }
    public function statusHistory(): HasMany { return $this->hasMany(CaseStatusHistory::class, 'case_id')->latest('changed_at'); }
    public function reopenings(): HasMany { return $this->hasMany(CaseReopening::class, 'case_id')->latest('reopened_at'); }
    public function interventions(): HasMany { return $this->hasMany(Intervention::class, 'case_id')->latest('activity_date'); }
    public function alerts(): HasMany { return $this->hasMany(Alert::class, 'case_id')->latest('alerted_at'); }
    public function referrals(): HasMany { return $this->hasMany(Referral::class, 'case_id')->latest('referral_date'); }
    public function protocols(): HasMany { return $this->hasMany(CaseProtocol::class, 'case_id')->latest('activated_at'); }
    public function reports(): HasMany { return $this->hasMany(Report::class, 'case_id')->latest('id'); }
    public function documents(): HasMany { return $this->hasMany(Document::class, 'case_id')->latest('id'); }
    public function commitments(): HasMany { return $this->hasMany(Commitment::class, 'case_id')->latest('due_at'); }
}
