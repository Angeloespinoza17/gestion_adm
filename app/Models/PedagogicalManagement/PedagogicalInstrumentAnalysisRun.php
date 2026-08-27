<?php

namespace App\Models\PedagogicalManagement;

use App\Enums\PedagogicalManagement\AnalysisRunStatus;
use App\Models\PedagogicalManagement\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PedagogicalInstrumentAnalysisRun extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'status' => AnalysisRunStatus::class,
            'extracted_data' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(PedagogicalInstrument::class, 'instrument_id');
    }

    public function instrumentFile(): BelongsTo
    {
        return $this->belongsTo(PedagogicalInstrumentFile::class, 'instrument_file_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validationResults(): HasMany
    {
        return $this->hasMany(PedagogicalInstrumentValidationResult::class, 'analysis_run_id');
    }
}
