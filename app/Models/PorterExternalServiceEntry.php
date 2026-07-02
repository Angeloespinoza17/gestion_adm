<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PorterExternalServiceEntry extends Model
{
    use HasFactory;

    public const STATUS_OPTIONS = [
        ['value' => 'en_curso', 'label' => 'En curso'],
        ['value' => 'finalizado', 'label' => 'Finalizado'],
        ['value' => 'rechazado', 'label' => 'Rechazado'],
    ];

    protected $fillable = [
        'responsible_staff_id',
        'maintenance_dependency_id',
        'registered_by',
        'closed_by',
        'status',
        'entered_at',
        'exited_at',
        'service_type',
        'company_name',
        'contact_name',
        'contact_rut',
        'phone',
        'vehicle_plate',
        'observations',
        'metadata',
    ];

    protected $casts = [
        'entered_at' => 'datetime:Y-m-d H:i:s',
        'exited_at' => 'datetime:Y-m-d H:i:s',
        'metadata' => 'array',
    ];

    public function responsibleStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_staff_id');
    }

    public function dependency(): BelongsTo
    {
        return $this->belongsTo(MaintenanceDependency::class, 'maintenance_dependency_id');
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
