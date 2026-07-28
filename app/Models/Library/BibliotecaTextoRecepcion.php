<?php

namespace App\Models\Library;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BibliotecaTextoRecepcion extends Model
{
    protected $table = 'biblioteca_texto_recepciones';

    protected $fillable = [
        'reception_code',
        'received_at',
        'source_name',
        'document_reference',
        'notes',
        'received_by_user_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'received_at' => 'date:Y-m-d',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(BibliotecaTextoRecepcionItem::class, 'biblioteca_texto_recepcion_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }
}
