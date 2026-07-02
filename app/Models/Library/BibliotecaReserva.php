<?php

namespace App\Models\Library;

use App\Models\CourseSection;
use App\Models\Staff;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BibliotecaReserva extends Model
{
    use HasFactory;

    public const STATUS_OPTIONS = [
        'solicitada',
        'aprobada',
        'rechazada',
        'retirada',
        'devuelta',
        'cancelada',
        'vencida',
    ];

    public const REQUESTER_TYPES = [
        'student',
        'staff',
        'teacher',
        'guardian',
        'course',
    ];

    protected $table = 'biblioteca_reservas';

    protected $fillable = [
        'reservation_code',
        'resource_type',
        'biblioteca_obra_id',
        'biblioteca_ejemplar_id',
        'biblioteca_prestamo_id',
        'requester_type',
        'requested_by_user_id',
        'student_profile_id',
        'staff_id',
        'course_section_id',
        'requested_at',
        'pickup_at',
        'expected_return_at',
        'returned_at',
        'purpose',
        'status',
        'responsible_user_id',
        'delivered_by_user_id',
        'received_by_user_id',
        'approval_notes',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'pickup_at' => 'datetime',
        'expected_return_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function obra(): BelongsTo
    {
        return $this->belongsTo(BibliotecaObra::class, 'biblioteca_obra_id');
    }

    public function ejemplar(): BelongsTo
    {
        return $this->belongsTo(BibliotecaEjemplar::class, 'biblioteca_ejemplar_id');
    }

    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(BibliotecaPrestamo::class, 'biblioteca_prestamo_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by_user_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
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
