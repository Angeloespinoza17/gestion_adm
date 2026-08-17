<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EdeValidationRun extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_ede_validation_runs';

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function export(): BelongsTo
    {
        return $this->belongsTo(EdeExport::class, 'ede_export_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(EdeValidationResult::class, 'validation_run_id');
    }
}
