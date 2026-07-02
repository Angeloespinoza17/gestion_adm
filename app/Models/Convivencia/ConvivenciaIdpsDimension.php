<?php

namespace App\Models\Convivencia;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConvivenciaIdpsDimension extends Model
{
    use HasFactory;

    protected $table = 'convivencia_idps_dimensions';

    protected $fillable = [
        'code',
        'name',
        'description',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function instruments(): HasMany
    {
        return $this->hasMany(ConvivenciaIdpsInstrument::class, 'dimension_id')->orderBy('name');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ConvivenciaIdpsResult::class, 'dimension_id')->latest('id');
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
