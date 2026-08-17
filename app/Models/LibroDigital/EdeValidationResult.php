<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EdeValidationResult extends LibroDigitalModel
{
    use HasPublicUlid;

    public const UPDATED_AT = null;

    protected $table = 'lcd_ede_validation_results';

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function validationRun(): BelongsTo
    {
        return $this->belongsTo(EdeValidationRun::class, 'validation_run_id');
    }

    public function export(): BelongsTo
    {
        return $this->belongsTo(EdeExport::class, 'ede_export_id');
    }
}
