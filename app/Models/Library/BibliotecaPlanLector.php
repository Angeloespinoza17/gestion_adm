<?php

namespace App\Models\Library;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BibliotecaPlanLector extends Model
{
    use HasFactory;

    public const STATUS_OPTIONS = [
        'planificado',
        'en_ejecucion',
        'finalizado',
        'suspendido',
    ];

    protected $table = 'biblioteca_plan_lector';

    protected $fillable = [
        'academic_year_id',
        'course_section_id',
        'subject',
        'responsible_staff_id',
        'biblioteca_obra_id',
        'period',
        'start_date',
        'end_date',
        'objective',
        'associated_activity',
        'evaluation_description',
        'required_copies',
        'available_copies',
        'fulfillment_percentage',
        'status',
        'notes',
        'attachments',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'required_copies' => 'integer',
        'available_copies' => 'integer',
        'fulfillment_percentage' => 'integer',
        'attachments' => 'array',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function responsibleStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_staff_id');
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(BibliotecaObra::class, 'biblioteca_obra_id');
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
