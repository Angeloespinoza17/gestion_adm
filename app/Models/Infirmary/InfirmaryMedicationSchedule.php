<?php

namespace App\Models\Infirmary;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InfirmaryMedicationSchedule extends Model
{
    use HasFactory;

    protected $table = 'infirmary_medication_schedules';

    protected $fillable = [
        'authorization_id',
        'dose_order',
        'scheduled_time',
        'active',
    ];

    protected $casts = [
        'dose_order' => 'integer',
        'active' => 'boolean',
    ];

    public function authorization(): BelongsTo
    {
        return $this->belongsTo(InfirmaryMedicationAuthorization::class, 'authorization_id');
    }

    public function administrations(): HasMany
    {
        return $this->hasMany(InfirmaryMedicationAdministration::class, 'schedule_id');
    }
}
