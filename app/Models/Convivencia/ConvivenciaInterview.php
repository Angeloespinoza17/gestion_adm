<?php

namespace App\Models\Convivencia;

use App\Models\CourseSection;
use App\Models\Staff;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConvivenciaInterview extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'convivencia_interviews';

    public const FOLLOW_UP_STATUS_OPTIONS = [
        ['value' => 'pendiente', 'label' => 'Pendiente'],
        ['value' => 'realizado', 'label' => 'Realizado'],
        ['value' => 'reprogramado', 'label' => 'Reprogramado'],
        ['value' => 'cerrado', 'label' => 'Cerrado'],
    ];

    protected $fillable = [
        'case_id',
        'student_profile_id',
        'course_section_id',
        'interview_type_item_id',
        'responsible_user_id',
        'responsible_staff_id',
        'interview_type_label',
        'interview_at',
        'motive',
        'topics',
        'agreements',
        'commitments',
        'follow_up_date',
        'follow_up_status',
        'internal_notes',
        'is_sensitive',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'interview_at' => 'datetime',
        'follow_up_date' => 'date:Y-m-d',
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

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaCatalogItem::class, 'interview_type_item_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function responsibleStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_staff_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConvivenciaInterviewParticipant::class, 'interview_id')->orderBy('id');
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
