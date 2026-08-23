<?php

namespace App\Models\Grades;

use App\Models\AcademicYear;
use App\Models\LibroDigital\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnnualGradeImport extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'source_date_from' => 'date:Y-m-d',
            'source_date_to' => 'date:Y-m-d',
            'metadata' => 'array',
            'completed_at' => 'datetime',
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

    public function rows(): HasMany
    {
        return $this->hasMany(AnnualGradeImportRow::class);
    }

    public function columns(): HasMany
    {
        return $this->hasMany(AnnualGradeImportColumn::class);
    }

    public function cells(): HasMany
    {
        return $this->hasMany(AnnualGradeImportCell::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
