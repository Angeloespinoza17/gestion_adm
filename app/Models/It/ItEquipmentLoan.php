<?php

namespace App\Models\It;

use App\Models\Staff;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItEquipmentLoan extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const REQUESTER_TYPES = [
        'funcionario',
        'estudiante',
        'apoderado',
        'externo',
        'otro',
    ];

    public const STATUS_OPTIONS = [
        'activo',
        'devuelto',
        'atrasado',
        'cancelado',
    ];

    public const RETURN_CONDITION_OPTIONS = [
        'bueno',
        'con_observaciones',
        'danado',
        'incompleto',
    ];

    protected $table = 'it_equipment_loans';

    protected $fillable = [
        'loan_code',
        'it_equipment_id',
        'requester_type',
        'requester_user_id',
        'requester_staff_id',
        'requester_student_profile_id',
        'requester_name_snapshot',
        'requester_rut_snapshot',
        'requester_contact_snapshot',
        'borrowed_at',
        'due_at',
        'returned_at',
        'purpose',
        'location_name',
        'delivered_by_user_id',
        'received_by_user_id',
        'status',
        'return_condition',
        'notes',
        'return_notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'borrowed_at' => 'datetime',
        'due_at' => 'datetime',
        'returned_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['activo', 'atrasado']);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'atrasado');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(ItEquipment::class, 'it_equipment_id')->withTrashed();
    }

    public function requesterUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function requesterStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'requester_staff_id');
    }

    public function requesterStudent(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'requester_student_profile_id');
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

    public function attachments(): MorphMany
    {
        return $this->morphMany(ItEquipmentAttachment::class, 'attachable')->latest('id');
    }
}
