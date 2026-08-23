<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumImportConflict extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_curriculum_import_conflicts';

    protected function casts(): array
    {
        return ['context' => 'array', 'resolved_at' => 'datetime'];
    }

    public function importFile(): BelongsTo
    {
        return $this->belongsTo(CurriculumImportFile::class, 'import_file_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(CurriculumImportCandidate::class, 'import_candidate_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
