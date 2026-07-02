<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffOrganigramRelation extends Model
{
    use HasFactory;

    public const RELATIONSHIP_OPTIONS = [
        ['value' => 'direct_manager', 'label' => 'Jefatura directa'],
        ['value' => 'acting_manager', 'label' => 'Jefatura subrogante'],
        ['value' => 'department_head', 'label' => 'Jefatura de departamento'],
        ['value' => 'academic_coordinator', 'label' => 'Coordinación académica'],
        ['value' => 'subdirector', 'label' => 'Subdirección'],
        ['value' => 'director', 'label' => 'Dirección'],
        ['value' => 'hr', 'label' => 'RRHH / Administración'],
        ['value' => 'other', 'label' => 'Otra relación'],
    ];

    protected $fillable = [
        'staff_id',
        'related_staff_id',
        'relationship_type',
        'custom_label',
        'priority',
        'is_primary',
        'active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'priority' => 'integer',
        'is_primary' => 'boolean',
        'active' => 'boolean',
    ];

    protected $appends = [
        'relationship_label',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }

    public function getRelationshipLabelAttribute(): string
    {
        if ($this->relationship_type === 'other' && $this->custom_label) {
            return $this->custom_label;
        }

        return collect(self::RELATIONSHIP_OPTIONS)
            ->firstWhere('value', $this->relationship_type)['label'] ?? $this->relationship_type;
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function relatedStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'related_staff_id');
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
