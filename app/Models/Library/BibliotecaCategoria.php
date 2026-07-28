<?php

namespace App\Models\Library;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BibliotecaCategoria extends Model
{
    protected $table = 'biblioteca_categorias';

    protected $fillable = [
        'name',
        'slug',
        'code',
        'color',
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

    public function obras(): HasMany
    {
        return $this->hasMany(BibliotecaObra::class, 'biblioteca_categoria_id');
    }

    public function subcategorias(): HasMany
    {
        return $this->hasMany(BibliotecaSubcategoria::class, 'biblioteca_categoria_id')
            ->orderBy('sort_order')
            ->orderBy('name');
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
