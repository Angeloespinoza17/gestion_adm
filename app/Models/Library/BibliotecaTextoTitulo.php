<?php

namespace App\Models\Library;

use App\Models\EducationLevel;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BibliotecaTextoTitulo extends Model
{
    protected $table = 'biblioteca_texto_titulos';

    protected $fillable = [
        'identity_key',
        'title',
        'subject',
        'publisher',
        'isbn',
        'education_level_id',
        'active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $hidden = ['identity_key'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class);
    }

    public function receptionItems(): HasMany
    {
        return $this->hasMany(BibliotecaTextoRecepcionItem::class, 'biblioteca_texto_titulo_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(BibliotecaTextoOrdenItem::class, 'biblioteca_texto_titulo_id');
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
