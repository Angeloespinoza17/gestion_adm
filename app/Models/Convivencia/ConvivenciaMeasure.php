<?php

namespace App\Models\Convivencia;

use App\Models\CourseSection;
use App\Models\Staff;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConvivenciaMeasure extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'convivencia_measures';

    public const STATUS_OPTIONS = [
        ['value' => 'asignada', 'label' => 'Asignada'],
        ['value' => 'en_proceso', 'label' => 'En proceso'],
        ['value' => 'cumplida', 'label' => 'Cumplida'],
        ['value' => 'incumplida', 'label' => 'Incumplida'],
        ['value' => 'reprogramada', 'label' => 'Reprogramada'],
        ['value' => 'cerrada', 'label' => 'Cerrada'],
    ];

    protected $fillable = [
        'case_id',
        'student_profile_id',
        'course_section_id',
        'measure_type_item_id',
        'responsible_user_id',
        'responsible_staff_id',
        'validated_by',
        'measure_type_label',
        'description',
        'training_objective',
        'assigned_at',
        'due_at',
        'status',
        'evidence_summary',
        'student_reflection',
        'repair_action',
        'responsible_notes',
        'closure_notes',
        'closed_at',
        'is_sensitive',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'due_at' => 'datetime',
        'closed_at' => 'datetime',
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
        return $this->belongsTo(ConvivenciaCatalogItem::class, 'measure_type_item_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function responsibleStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_staff_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
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
