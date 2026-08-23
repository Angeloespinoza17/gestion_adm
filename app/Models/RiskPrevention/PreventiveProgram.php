<?php

namespace App\Models\RiskPrevention;

use App\Models\MaintenanceDependency;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreventiveProgram extends Model
{
    protected $table = 'prevent_preventive_programs';

    protected $guarded = [];

    protected $casts = ['generated_at' => 'datetime', 'due_to_be_prepared_at' => 'date', 'approved_at' => 'datetime', 'progress_percentage' => 'integer'];

    public function version(): BelongsTo
    {
        return $this->belongsTo(RiskMatrixVersion::class, 'risk_matrix_version_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(PreventiveProgramAction::class, 'preventive_program_id')->orderBy('due_date');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(MaintenanceDependency::class, 'work_center_id');
    }
}
