<?php

namespace App\Models\Convivencia;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConvivenciaIdpsInstrument extends Model
{
    use HasFactory;

    protected $table = 'convivencia_idps_instruments';

    protected $fillable = [
        'dimension_id',
        'name',
        'description',
        'response_type',
        'scale_label',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaIdpsDimension::class, 'dimension_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ConvivenciaIdpsResult::class, 'instrument_id')->latest('id');
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
