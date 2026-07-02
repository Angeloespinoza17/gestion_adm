<?php

namespace App\Models\Convivencia;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\Staff;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConvivenciaDailyLog extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'convivencia_daily_logs';

    public const STATUS_OPTIONS = [
        ['value' => 'registrado', 'label' => 'Registrado'],
        ['value' => 'revisado', 'label' => 'Revisado'],
        ['value' => 'convertido_caso', 'label' => 'Convertido en caso'],
        ['value' => 'convertido_derivacion', 'label' => 'Convertido en derivación'],
        ['value' => 'cerrado', 'label' => 'Cerrado'],
    ];

    protected $fillable = [
        'case_id',
        'generated_derivation_id',
        'academic_year_id',
        'course_section_id',
        'student_profile_id',
        'daily_log_type_item_id',
        'inspector_user_id',
        'inspector_staff_id',
        'happened_at',
        'daily_log_type_label',
        'place',
        'description',
        'immediate_action',
        'involved_snapshot',
        'guardian_informed',
        'guardian_contact_note',
        'status',
        'is_sensitive',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'happened_at' => 'datetime',
        'involved_snapshot' => 'array',
        'guardian_informed' => 'boolean',
        'is_sensitive' => 'boolean',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaCase::class, 'case_id');
    }

    public function generatedDerivation(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaDerivation::class, 'generated_derivation_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaCatalogItem::class, 'daily_log_type_item_id');
    }

    public function inspectorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_user_id');
    }

    public function inspectorStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'inspector_staff_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(ConvivenciaAttachment::class, 'attachable')->latest('id');
    }

    public function statusLogs(): MorphMany
    {
        return $this->morphMany(ConvivenciaStatusLog::class, 'loggable')->latest('changed_at')->latest('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
