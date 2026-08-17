<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EdeExportFile extends LibroDigitalModel
{
    use HasPublicUlid;

    public const UPDATED_AT = null;

    protected $table = 'lcd_ede_export_files';

    protected function casts(): array
    {
        return ['encrypted' => 'boolean', 'encryption_metadata' => 'array'];
    }

    public function export(): BelongsTo
    {
        return $this->belongsTo(EdeExport::class, 'ede_export_id');
    }
}
