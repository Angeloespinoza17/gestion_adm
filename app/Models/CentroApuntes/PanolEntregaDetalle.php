<?php

namespace App\Models\CentroApuntes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PanolEntregaDetalle extends Model
{
    use HasFactory;

    protected $table = 'panol_entrega_detalles';

    protected $hidden = [
        'unit_cost_estimated',
        'line_total_estimated',
    ];

    protected $fillable = [
        'panol_entrega_id',
        'insumo_id',
        'insumo_name_snapshot',
        'quantity',
        'unit_cost_estimated',
        'line_total_estimated',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost_estimated' => 'decimal:2',
        'line_total_estimated' => 'decimal:2',
    ];

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(PanolEntrega::class, 'panol_entrega_id');
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(PanolInsumo::class, 'insumo_id');
    }
}
