<?php

namespace App\Models\Convivencia;

use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConvivenciaIdpsPeriod extends Model
{
    use HasFactory;

    protected $table = 'convivencia_idps_periods';

    protected $fillable = [
        'academic_year_id',
        'name',
        'starts_on',
        'ends_on',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'starts_on' => 'date:Y-m-d',
        'ends_on' => 'date:Y-m-d',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(ConvivenciaIdpsResult::class, 'period_id')->latest('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
