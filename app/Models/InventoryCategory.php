<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'code_prefix',
        'description',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function subcategories(): HasMany
    {
        return $this->hasMany(InventorySubcategory::class, 'category_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'category_id');
    }
}

