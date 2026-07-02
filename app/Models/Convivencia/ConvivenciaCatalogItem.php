<?php

namespace App\Models\Convivencia;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConvivenciaCatalogItem extends Model
{
    use HasFactory;

    protected $table = 'convivencia_catalog_items';

    protected $fillable = [
        'parent_id',
        'group',
        'code',
        'name',
        'description',
        'color',
        'metadata',
        'sort_order',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'metadata' => 'array',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
