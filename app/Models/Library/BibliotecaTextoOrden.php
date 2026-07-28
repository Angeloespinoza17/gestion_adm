<?php

namespace App\Models\Library;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BibliotecaTextoOrden extends Model
{
    public const STATUSES = ['borrador', 'preparada', 'parcial', 'cerrada', 'cancelada'];

    protected $table = 'biblioteca_texto_ordenes';

    protected $fillable = [
        'order_code',
        'academic_year_id',
        'education_level_id',
        'course_section_id',
        'status',
        'prepared_at',
        'notes',
        'prepared_by_user_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'prepared_at' => 'date:Y-m-d',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class);
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BibliotecaTextoOrdenItem::class, 'biblioteca_texto_orden_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(BibliotecaTextoEntrega::class, 'biblioteca_texto_orden_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
    }
}
