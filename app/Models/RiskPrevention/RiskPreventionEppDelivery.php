<?php

namespace App\Models\RiskPrevention;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskPreventionEppDelivery extends Model
{
    public const STATUS_VIGENTE = 'vigente';
    public const STATUS_POR_REPONER = 'por_reponer';
    public const STATUS_REPUESTO = 'repuesto';

    protected $table = 'prevent_epp_deliveries';

    protected $fillable = [
        'epp_item_id',
        'employee_name',
        'quantity',
        'delivered_at',
        'replacement_due_at',
        'status',
        'observations',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'delivered_at' => 'date',
        'replacement_due_at' => 'date',
    ];

    protected $appends = [
        'days_until_replacement',
        'current_status',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(RiskPreventionEppItem::class, 'epp_item_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getDaysUntilReplacementAttribute(): ?int
    {
        if (!$this->replacement_due_at) {
            return null;
        }

        return now()->startOfDay()->diffInDays($this->replacement_due_at->copy()->startOfDay(), false);
    }

    public function getCurrentStatusAttribute(): string
    {
        if ($this->status === self::STATUS_REPUESTO) {
            return self::STATUS_REPUESTO;
        }

        if ($this->days_until_replacement !== null && $this->days_until_replacement <= 30) {
            return self::STATUS_POR_REPONER;
        }

        return self::STATUS_VIGENTE;
    }
}
