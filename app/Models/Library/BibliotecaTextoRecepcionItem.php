<?php

namespace App\Models\Library;

use App\Models\EducationLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BibliotecaTextoRecepcionItem extends Model
{
    protected $table = 'biblioteca_texto_recepcion_items';

    protected $fillable = [
        'biblioteca_texto_recepcion_id',
        'biblioteca_texto_titulo_id',
        'education_level_id',
        'title',
        'subject',
        'publisher',
        'quantity_received',
        'unit_cost',
        'notes',
    ];

    protected $casts = [
        'quantity_received' => 'integer',
        'unit_cost' => 'decimal:2',
    ];

    public function reception(): BelongsTo
    {
        return $this->belongsTo(BibliotecaTextoRecepcion::class, 'biblioteca_texto_recepcion_id');
    }

    public function textoTitulo(): BelongsTo
    {
        return $this->belongsTo(BibliotecaTextoTitulo::class, 'biblioteca_texto_titulo_id');
    }

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class);
    }
}
