<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SystemModule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'frontend_route',
        'icon',
        'sort_order',
        'active',
        'parent_id',
    ];

    protected $casts = [
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(SystemModule::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(SystemModule::class, 'parent_id')->orderBy('sort_order');
    }

    public function permissionGroups(): HasMany
    {
        return $this->hasMany(PermissionGroup::class)->orderBy('sort_order');
    }
}
