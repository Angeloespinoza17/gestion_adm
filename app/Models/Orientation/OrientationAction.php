<?php

namespace App\Models\Orientation;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrientationAction extends Model
{
    use HasFactory;

    public const STATUSES = ['planned', 'in_progress', 'completed', 'postponed', 'cancelled'];

    protected $fillable = [
        'orientation_plan_id',
        'title',
        'objective',
        'description',
        'target_levels',
        'planned_verification_means',
        'material_resources',
        'responsible_summary',
        'start_date',
        'end_date',
        'status',
        'progress',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'progress' => 'integer',
        'sort_order' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(OrientationPlan::class, 'orientation_plan_id');
    }

    public function responsibleUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'orientation_action_responsibles')->withTimestamps();
    }

    public function relatedPlans(): BelongsToMany
    {
        return $this->belongsToMany(OrientationRelatedPlan::class, 'orientation_action_related_plan')->withTimestamps();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(OrientationActivity::class)->orderBy('starts_at')->orderBy('id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(OrientationEvidence::class)->latest('occurred_on')->latest('id');
    }
}
