<?php

namespace App\Models\Remuneration;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RemunerationBookImport extends RemunerationModel
{
    use SoftDeletes;

    protected $casts = [
        'book_period' => 'date:Y-m-d',
        'year' => 'integer',
        'month' => 'integer',
        'row_count' => 'integer',
        'matched_count' => 'integer',
        'unmatched_count' => 'integer',
        'error_count' => 'integer',
        'gross_total' => 'integer',
        'net_total' => 'integer',
        'total_deductions' => 'integer',
        'employer_contributions' => 'integer',
        'summary' => 'array',
        'errors' => 'array',
        'metadata' => 'array',
        'imported_at' => 'datetime:Y-m-d H:i',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(RemunerationPeriod::class, 'period_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(RemunerationBookImportRow::class, 'book_import_id')->orderBy('row_number');
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(RemunerationPayroll::class, 'book_import_id');
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
