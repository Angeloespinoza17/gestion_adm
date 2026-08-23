<?php

namespace App\Models\Grades;

use App\Models\LibroDigital\StudentResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnualGradeImportCell extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'numeric_value' => 'float',
            'applied_at' => 'datetime',
        ];
    }

    public function annualImport(): BelongsTo
    {
        return $this->belongsTo(AnnualGradeImport::class, 'annual_grade_import_id');
    }

    public function row(): BelongsTo
    {
        return $this->belongsTo(AnnualGradeImportRow::class, 'annual_grade_import_row_id');
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(AnnualGradeImportColumn::class, 'annual_grade_import_column_id');
    }

    public function studentResult(): BelongsTo
    {
        return $this->belongsTo(StudentResult::class);
    }
}
