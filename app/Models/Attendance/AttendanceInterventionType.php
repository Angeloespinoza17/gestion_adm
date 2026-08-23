<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceInterventionType extends Model
{
    protected $fillable = ['code', 'name', 'category', 'family_contact', 'sensitive', 'active', 'sort_order', 'created_by', 'updated_by'];

    protected $casts = ['family_contact' => 'boolean', 'sensitive' => 'boolean', 'active' => 'boolean'];

    public function interventions(): HasMany
    {
        return $this->hasMany(AttendanceIntervention::class, 'intervention_type_id');
    }
}
