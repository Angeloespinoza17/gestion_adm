<?php

namespace App\Models\Orientation;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrientationActivity extends Model
{
    use HasFactory;

    public const STATUSES = ['scheduled', 'in_progress', 'completed', 'cancelled'];

    protected $fillable = [
        'orientation_action_id',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'status',
        'contribution_percent',
        'completion_percent',
        'location',
        'participants',
        'attendee_count',
        'results',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'attendee_count' => 'integer',
        'contribution_percent' => 'integer',
        'completion_percent' => 'integer',
    ];

    public function action(): BelongsTo
    {
        return $this->belongsTo(OrientationAction::class, 'orientation_action_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(OrientationEvidence::class)->latest('occurred_on')->latest('id');
    }
}
