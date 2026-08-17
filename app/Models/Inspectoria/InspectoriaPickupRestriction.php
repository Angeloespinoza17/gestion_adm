<?php

namespace App\Models\Inspectoria;

use App\Models\CourseSection;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectoriaPickupRestriction extends Model
{
    public const TYPES = [
        'orden_alejamiento' => 'Orden de alejamiento',
        'prohibicion_judicial' => 'Prohibición judicial',
        'restriccion_familiar' => 'Restricción familiar',
        'custodia_especial' => 'Custodia o cuidado especial',
        'persona_no_autorizada' => 'Persona no autorizada',
        'otra' => 'Otra restricción',
    ];

    protected $table = 'inspectoria_pickup_restrictions';

    protected $fillable = [
        'student_profile_id', 'course_section_id', 'restricted_person_name',
        'restricted_person_rut', 'restricted_person_relationship', 'restriction_type',
        'reason', 'legal_reference', 'starts_on', 'ends_on', 'active',
        'created_by_user_id', 'updated_by_user_id',
    ];

    protected $casts = [
        'starts_on' => 'date:Y-m-d',
        'ends_on' => 'date:Y-m-d',
        'active' => 'boolean',
    ];

    protected $appends = ['restriction_code', 'restriction_type_label'];

    public function getRestrictionCodeAttribute(): string
    {
        return 'RES-'.str_pad((string) $this->getKey(), 6, '0', STR_PAD_LEFT);
    }

    public function getRestrictionTypeLabelAttribute(): string
    {
        return self::TYPES[$this->restriction_type] ?? 'Restricción de retiro';
    }

    public function scopeActiveOn(Builder $query, mixed $date = null): Builder
    {
        $effectiveDate = $date ?: today();

        return $query->where('active', true)
            ->whereDate('starts_on', '<=', $effectiveDate)
            ->where(fn (Builder $inner) => $inner
                ->whereNull('ends_on')
                ->orWhereDate('ends_on', '>=', $effectiveDate));
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
