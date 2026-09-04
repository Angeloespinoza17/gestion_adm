<?php

namespace App\Models\Orientation;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OrientationRelatedPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'orientation_plan_id',
        'name',
        'category',
        'description',
        'reference_url',
        'status',
        'created_by',
        'updated_by',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(OrientationPlan::class, 'orientation_plan_id');
    }

    public function actions(): BelongsToMany
    {
        return $this->belongsToMany(OrientationAction::class, 'orientation_action_related_plan')->withTimestamps();
    }
}
