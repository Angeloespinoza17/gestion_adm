<?php

namespace App\Models\Library;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BibliotecaUbicacion extends Model
{
    public const TYPES = ['sala', 'sector', 'estante', 'repisa'];

    public const AUDIENCE_TYPES = ['basica', 'media', 'mixta'];

    protected $table = 'biblioteca_ubicaciones';

    protected $fillable = [
        'parent_id',
        'type',
        'name',
        'code',
        'audience_type',
        'sort_order',
        'active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function obras(): HasMany
    {
        return $this->hasMany(BibliotecaObra::class, 'biblioteca_ubicacion_id');
    }

    public function ejemplares(): HasMany
    {
        return $this->hasMany(BibliotecaEjemplar::class, 'biblioteca_ubicacion_id');
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
