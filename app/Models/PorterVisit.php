<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PorterVisit extends Model
{
    use HasFactory;

    public const STATUS_OPTIONS = [
        ['value' => 'en_curso', 'label' => 'En curso'],
        ['value' => 'finalizada', 'label' => 'Finalizada'],
        ['value' => 'rechazada', 'label' => 'Rechazada'],
    ];

    protected $fillable = [
        'visited_staff_id',
        'visited_department_id',
        'registered_by',
        'closed_by',
        'status',
        'entered_at',
        'exited_at',
        'visitor_name',
        'visitor_rut',
        'purpose',
        'visited_person_label',
        'contact_phone',
        'observations',
        'metadata',
    ];

    protected $casts = [
        'entered_at' => 'datetime:Y-m-d H:i:s',
        'exited_at' => 'datetime:Y-m-d H:i:s',
        'metadata' => 'array',
    ];

    public function visitedStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'visited_staff_id');
    }

    public function visitedDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'visited_department_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function logs(): MorphMany
    {
        return $this->morphMany(PorterMovementLog::class, 'loggable')->latest('performed_at')->latest('id');
    }
}
