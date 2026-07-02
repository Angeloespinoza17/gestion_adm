<?php

namespace App\Models\Library;

use App\Models\CourseSection;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BibliotecaUsoEspacio extends Model
{
    use HasFactory;

    public const ACTIVITY_TYPES = [
        'clase',
        'lectura_silenciosa',
        'investigacion',
        'taller',
        'cuentacuentos',
        'reunion',
        'charla',
        'actividad_pastoral',
        'actividad_convivencia',
        'actividad_pie',
        'evaluacion',
        'otro',
    ];

    public const STATUS_OPTIONS = [
        'solicitada',
        'aprobada',
        'rechazada',
        'realizada',
        'cancelada',
    ];

    protected $table = 'biblioteca_uso_espacios';

    protected $fillable = [
        'biblioteca_espacio_id',
        'activity_type',
        'title',
        'course_section_id',
        'responsible_staff_id',
        'requested_by_user_id',
        'attendee_count',
        'requested_resources',
        'start_at',
        'end_at',
        'status',
        'observations',
        'evidence',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'requested_resources' => 'array',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'attendee_count' => 'integer',
        'evidence' => 'array',
    ];

    public function espacio(): BelongsTo
    {
        return $this->belongsTo(BibliotecaEspacio::class, 'biblioteca_espacio_id');
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function responsibleStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_staff_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
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
