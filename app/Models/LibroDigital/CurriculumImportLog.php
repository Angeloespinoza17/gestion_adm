<?php

namespace App\Models\LibroDigital;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumImportLog extends LibroDigitalModel
{
    protected $table = 'lcd_curriculum_import_logs';

    protected function casts(): array
    {
        return ['context' => 'array', 'progress' => 'integer', 'occurred_at' => 'datetime'];
    }

    public function importFile(): BelongsTo
    {
        return $this->belongsTo(CurriculumImportFile::class, 'import_file_id');
    }
}
