<?php

namespace App\Models\Pme;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmeDimension extends Model
{
    use SoftDeletes;

    protected $table = 'pme_dimensiones';

    protected $fillable = [
        'name',
        'description',
        'active',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function objectives(): HasMany
    {
        return $this->hasMany(PmeObjective::class, 'pme_dimension_id')->latest('id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(PmeAction::class, 'pme_dimension_id')->latest('id');
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
