<?php

namespace App\Models\CentroApuntes;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PanolEntrega extends Model
{
    use HasFactory;

    public const STATUS_OPTIONS = [
        'solicitada',
        'aprobada',
        'entregada',
        'rechazada',
        'anulada',
    ];

    protected $table = 'panol_entregas';

    protected $fillable = [
        'delivery_code',
        'requested_by_user_id',
        'requested_by_name_snapshot',
        'withdrawn_by_user_id',
        'withdrawn_by_name_snapshot',
        'department_id',
        'department_name_snapshot',
        'requested_at',
        'approved_at',
        'delivered_at',
        'approved_by_user_id',
        'delivered_by_user_id',
        'status',
        'total_estimated_cost',
        'observations',
        'receipt_notes',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'requested_at' => 'datetime:Y-m-d H:i',
        'approved_at' => 'datetime:Y-m-d H:i',
        'delivered_at' => 'datetime:Y-m-d H:i',
        'total_estimated_cost' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function withdrawnBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'withdrawn_by_user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by_user_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(PanolEntregaDetalle::class, 'panol_entrega_id')->orderBy('id');
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
