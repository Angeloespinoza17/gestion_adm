<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PorterKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'maintenance_dependency_id',
        'department_id',
        'code',
        'name',
        'observations',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function dependency(): BelongsTo
    {
        return $this->belongsTo(MaintenanceDependency::class, 'maintenance_dependency_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(PorterKeyLoan::class)->latest('checked_out_at')->latest('id');
    }

    public function activeLoan(): HasMany
    {
        return $this->hasMany(PorterKeyLoan::class)->where('status', 'prestada');
    }

    public function logs(): MorphMany
    {
        return $this->morphMany(PorterMovementLog::class, 'loggable')->latest('performed_at')->latest('id');
    }
}
