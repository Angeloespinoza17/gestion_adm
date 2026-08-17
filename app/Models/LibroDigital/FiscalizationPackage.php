<?php

namespace App\Models\LibroDigital;

use App\Models\AcademicYear;
use App\Models\LibroDigital\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalizationPackage extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_fiscalization_packages';

    protected function casts(): array
    {
        return [
            'scope_snapshot' => 'array',
            'manifest' => 'array',
            'generated_at' => 'datetime',
            'released_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function edeExport(): BelongsTo
    {
        return $this->belongsTo(EdeExport::class);
    }
}
