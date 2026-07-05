<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryDependencyAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'maintenance_dependency_id',
        'audited_at',
        'expected_items_count',
        'found_items_count',
        'missing_items_count',
        'critical_items_count',
        'low_stock_items_count',
        'notes',
        'audited_by',
    ];

    protected $casts = [
        'audited_at' => 'datetime:Y-m-d H:i',
        'expected_items_count' => 'integer',
        'found_items_count' => 'integer',
        'missing_items_count' => 'integer',
        'critical_items_count' => 'integer',
        'low_stock_items_count' => 'integer',
    ];

    public function dependency(): BelongsTo
    {
        return $this->belongsTo(MaintenanceDependency::class, 'maintenance_dependency_id');
    }

    public function auditedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'audited_by');
    }
}
