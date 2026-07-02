<?php

namespace App\Models\Infirmary;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfirmaryMedicationMovement extends Model
{
    use HasFactory;

    public const TYPE_INGRESO = 'ingreso';
    public const TYPE_SALIDA = 'salida';
    public const TYPE_ADMINISTRACION = 'administracion';
    public const TYPE_AJUSTE = 'ajuste';
    public const TYPE_PERDIDA = 'perdida';
    public const TYPE_VENCIMIENTO = 'vencimiento';
    public const TYPE_DONACION = 'donacion';
    public const TYPE_REVERSA = 'reversa';

    protected $table = 'infirmary_medication_movements';

    protected $fillable = [
        'medication_id',
        'movement_type',
        'quantity',
        'stock_before',
        'stock_after',
        'reason',
        'notes',
        'reference_type',
        'reference_id',
        'moved_at',
        'performed_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'stock_before' => 'decimal:2',
        'stock_after' => 'decimal:2',
        'moved_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function medication(): BelongsTo
    {
        return $this->belongsTo(InfirmaryMedication::class, 'medication_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
