<?php

namespace App\Models\Orientation;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrientationCalendarizationEntry extends Model
{
    use HasFactory;

    public const LEVEL_GROUPS = ['primary', 'secondary', 'third', 'fourth'];

    public const STATUSES = ['planned', 'confirmed', 'completed', 'cancelled'];

    public const CATEGORIES = [
        'activity',
        'rice',
        'coexistence',
        'socioemotional',
        'vocational',
        'affectivity',
        'idps',
        'evaluation',
        'no_planning',
        'vacation',
    ];

    protected $fillable = [
        'orientation_plan_id',
        'orientation_action_id',
        'level_group',
        'title',
        'description',
        'category',
        'start_date',
        'end_date',
        'status',
        'source_key',
        'source_label',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(OrientationPlan::class, 'orientation_plan_id');
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(OrientationAction::class, 'orientation_action_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
