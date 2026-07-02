<?php

namespace App\Models\RiskPrevention;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiskPreventionTraining extends Model
{
    protected $table = 'prevent_trainings';

    protected $fillable = [
        'name',
        'training_type',
        'training_date',
        'modality',
        'evidence_path',
        'evidence_name',
        'observations',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'training_date' => 'date',
    ];

    public function participants(): HasMany
    {
        return $this->hasMany(RiskPreventionTrainingParticipant::class, 'training_id')->orderBy('employee_name');
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
