<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumImportCandidate extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_curriculum_import_candidates';

    protected function casts(): array
    {
        return [
            'structured_payload' => 'array',
            'confidence' => 'decimal:4',
            'physical_page' => 'integer',
            'suggested_existing_id' => 'integer',
            'warnings' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function importFile(): BelongsTo
    {
        return $this->belongsTo(CurriculumImportFile::class, 'import_file_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_candidate_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_candidate_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
