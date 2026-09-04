<?php

namespace App\Models\Orientation;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrientationPlan extends Model
{
    use HasFactory;

    public const STATUSES = ['draft', 'active', 'completed', 'archived'];

    protected $fillable = [
        'year',
        'title',
        'general_objective',
        'description',
        'status',
        'owner_user_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'year' => 'integer',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(OrientationAction::class)->orderBy('sort_order')->orderBy('id');
    }

    public function relatedPlans(): HasMany
    {
        return $this->hasMany(OrientationRelatedPlan::class)->orderBy('name');
    }

    public function calendarizationEntries(): HasMany
    {
        return $this->hasMany(OrientationCalendarizationEntry::class)
            ->orderBy('start_date')
            ->orderBy('level_group')
            ->orderBy('id');
    }
}
