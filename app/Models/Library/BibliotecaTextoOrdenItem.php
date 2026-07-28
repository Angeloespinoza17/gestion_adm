<?php

namespace App\Models\Library;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BibliotecaTextoOrdenItem extends Model
{
    protected $table = 'biblioteca_texto_orden_items';

    protected $fillable = [
        'biblioteca_texto_orden_id',
        'biblioteca_texto_titulo_id',
        'title',
        'subject',
        'quantity_required',
        'quantity_available',
        'quantity_assigned',
        'shortage_quantity',
        'notes',
    ];

    protected $casts = [
        'quantity_required' => 'integer',
        'quantity_available' => 'integer',
        'quantity_assigned' => 'integer',
        'shortage_quantity' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(BibliotecaTextoOrden::class, 'biblioteca_texto_orden_id');
    }

    public function textoTitulo(): BelongsTo
    {
        return $this->belongsTo(BibliotecaTextoTitulo::class, 'biblioteca_texto_titulo_id');
    }

    public function deliveryItems(): HasMany
    {
        return $this->hasMany(BibliotecaTextoEntregaItem::class, 'biblioteca_texto_orden_item_id');
    }
}
