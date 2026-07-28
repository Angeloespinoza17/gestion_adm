<?php

namespace App\Models\Library;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BibliotecaTextoEntregaItem extends Model
{
    protected $table = 'biblioteca_texto_entrega_items';

    protected $fillable = [
        'biblioteca_texto_entrega_id',
        'biblioteca_texto_orden_item_id',
        'quantity',
        'status',
        'delivered_at',
        'pending_reason',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'delivered_at' => 'datetime',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(BibliotecaTextoEntrega::class, 'biblioteca_texto_entrega_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(BibliotecaTextoOrdenItem::class, 'biblioteca_texto_orden_item_id');
    }
}
