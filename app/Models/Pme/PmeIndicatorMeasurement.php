<?php

namespace App\Models\Pme;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmeIndicatorMeasurement extends Model
{
    protected $table = 'pme_indicador_mediciones';

    protected $fillable = [
        'pme_indicator_id',
        'measured_at',
        'measured_value',
        'compliance_percentage',
        'state',
        'information_source',
        'analysis',
        'observations',
        'responsible_user_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'measured_at' => 'date:Y-m-d',
        'measured_value' => 'decimal:2',
        'compliance_percentage' => 'decimal:2',
    ];

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(PmeIndicator::class, 'pme_indicator_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(PmeEvidence::class, 'pme_indicator_measurement_id')->latest('id');
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
