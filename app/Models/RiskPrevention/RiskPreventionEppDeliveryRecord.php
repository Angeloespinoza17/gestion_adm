<?php

namespace App\Models\RiskPrevention;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiskPreventionEppDeliveryRecord extends Model
{
    public const FORM_CODE = 'FO-PREV-03';

    public const FORM_REVISION = '01';

    protected $table = 'prevent_epp_delivery_records';

    protected $fillable = [
        'folio',
        'form_code',
        'form_revision',
        'staff_id',
        'employee_name_snapshot',
        'employee_rut_snapshot',
        'employee_position_snapshot',
        'delivered_at',
        'received_conformity',
        'received_conformity_at',
        'delivered_by_name',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'delivered_at' => 'date:Y-m-d',
        'received_conformity' => 'boolean',
        'received_conformity_at' => 'datetime',
    ];

    protected $appends = [
        'total_units',
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(RiskPreventionEppDelivery::class, 'delivery_record_id')
            ->orderBy('id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getTotalUnitsAttribute(): int
    {
        if ($this->relationLoaded('deliveries')) {
            return (int) $this->deliveries->sum('quantity');
        }

        return (int) $this->deliveries()->sum('quantity');
    }
}
