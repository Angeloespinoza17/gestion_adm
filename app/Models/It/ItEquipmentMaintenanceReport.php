<?php

namespace App\Models\It;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItEquipmentMaintenanceReport extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const TYPE_OPTIONS = [
        'preventiva',
        'correctiva',
        'diagnostico',
        'limpieza',
        'instalacion',
        'actualizacion',
        'reparacion',
        'otra',
    ];

    public const STATUS_OPTIONS = [
        'borrador',
        'finalizado',
        'pendiente_revision',
        'cerrado',
    ];

    protected $table = 'it_equipment_maintenance_reports';

    protected $fillable = [
        'maintenance_code',
        'it_equipment_id',
        'maintenance_date',
        'maintenance_type',
        'technician_user_id',
        'technician_name_snapshot',
        'reason',
        'diagnosis',
        'actions_performed',
        'spare_parts',
        'cost_amount',
        'initial_equipment_status',
        'final_equipment_status',
        'next_maintenance_at',
        'observations',
        'status',
        'closed_at',
        'closed_by_user_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'maintenance_date' => 'datetime',
        'cost_amount' => 'decimal:2',
        'next_maintenance_at' => 'date:Y-m-d',
        'closed_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function scopePending($query)
    {
        return $query->whereIn('status', ['borrador', 'finalizado', 'pendiente_revision']);
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'cerrado');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(ItEquipment::class, 'it_equipment_id')->withTrashed();
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
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
