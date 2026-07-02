<?php

namespace App\Models\CentroApuntes;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PanolMovimiento extends Model
{
    use HasFactory;

    public const TYPE_OPTIONS = [
        'ingreso',
        'salida',
        'ajuste',
        'perdida',
        'devolucion',
        'vencimiento',
        'baja',
    ];

    protected $table = 'panol_movimientos';

    protected $fillable = [
        'insumo_id',
        'movement_type',
        'quantity',
        'stock_before',
        'stock_after',
        'moved_at',
        'responsible_user_id',
        'requested_by_user_id',
        'department_id',
        'reason',
        'document_reference',
        'observations',
        'reference_type',
        'reference_id',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'stock_before' => 'decimal:2',
        'stock_after' => 'decimal:2',
        'moved_at' => 'datetime:Y-m-d H:i',
        'metadata' => 'array',
    ];

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(PanolInsumo::class, 'insumo_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
