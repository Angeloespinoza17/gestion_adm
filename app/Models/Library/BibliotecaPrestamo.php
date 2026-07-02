<?php

namespace App\Models\Library;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\Staff;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BibliotecaPrestamo extends Model
{
    use HasFactory;

    public const BORROWER_TYPES = [
        'student',
        'staff',
        'teacher',
        'guardian',
        'course',
    ];

    public const STATUS_OPTIONS = [
        'activo',
        'devuelto',
        'vencido',
        'renovado',
        'perdido',
        'danado',
        'cancelado',
    ];

    protected $table = 'biblioteca_prestamos';

    protected $fillable = [
        'loan_code',
        'batch_code',
        'borrower_type',
        'user_id',
        'student_profile_id',
        'staff_id',
        'course_section_id',
        'academic_year_id',
        'biblioteca_obra_id',
        'biblioteca_ejemplar_id',
        'borrower_name_snapshot',
        'course_name_snapshot',
        'borrowed_at',
        'due_at',
        'returned_at',
        'status',
        'renewed_count',
        'overdue_days',
        'returned_condition',
        'notes',
        'audit_trail',
        'lost_reported_at',
        'damaged_reported_at',
        'delivered_by_user_id',
        'received_by_user_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'borrowed_at' => 'datetime',
        'due_at' => 'date:Y-m-d',
        'returned_at' => 'datetime',
        'audit_trail' => 'array',
        'lost_reported_at' => 'datetime',
        'damaged_reported_at' => 'datetime',
        'renewed_count' => 'integer',
        'overdue_days' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(BibliotecaObra::class, 'biblioteca_obra_id');
    }

    public function ejemplar(): BelongsTo
    {
        return $this->belongsTo(BibliotecaEjemplar::class, 'biblioteca_ejemplar_id');
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(BibliotecaReserva::class, 'biblioteca_prestamo_id')->latest('requested_at');
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
