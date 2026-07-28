<?php

namespace App\Models\Library;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BibliotecaSubcategoria extends Model
{
    protected $table = 'biblioteca_subcategorias';

    protected $fillable = [
        'biblioteca_categoria_id',
        'name',
        'slug',
        'description',
        'sort_order',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'active' => 'boolean',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(BibliotecaCategoria::class, 'biblioteca_categoria_id');
    }

    public function obras(): HasMany
    {
        return $this->hasMany(BibliotecaObra::class, 'biblioteca_subcategoria_id');
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
