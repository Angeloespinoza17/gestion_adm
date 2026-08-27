<?php

namespace App\Models\PedagogicalManagement;

use App\Models\PedagogicalManagement\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PedagogicalInstrumentValidationResult extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'detected_value' => 'array', 'expected_value' => 'array', 'page_number' => 'integer',
            'is_blocking' => 'boolean', 'resolved_at' => 'datetime',
        ];
    }

    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(PedagogicalInstrumentAnalysisRun::class, 'analysis_run_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PedagogicalInstrumentValidationEvent::class, 'validation_result_id')->latest('performed_at');
    }

    public function unresolvedBlocking(): bool
    {
        return $this->is_blocking && $this->resolved_at === null && in_array($this->outcome, ['fail', 'warning'], true);
    }
}
