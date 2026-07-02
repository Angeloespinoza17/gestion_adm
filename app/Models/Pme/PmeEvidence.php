<?php

namespace App\Models\Pme;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmeEvidence extends Model
{
    use SoftDeletes;

    protected $table = 'pme_evidencias';

    protected $fillable = [
        'pme_action_id',
        'pme_activity_id',
        'pme_milestone_id',
        'pme_indicator_measurement_id',
        'pme_goal_measurement_id',
        'pme_reflective_monitoring_id',
        'evidence_type',
        'name',
        'description',
        'uploaded_at',
        'uploaded_by',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'review_status',
        'reviewed_at',
        'reviewed_by',
        'observations',
        'review_comments',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'file_size' => 'integer',
    ];

    public function action(): BelongsTo
    {
        return $this->belongsTo(PmeAction::class, 'pme_action_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(PmeActivity::class, 'pme_activity_id');
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(PmeMilestone::class, 'pme_milestone_id');
    }

    public function indicatorMeasurement(): BelongsTo
    {
        return $this->belongsTo(PmeIndicatorMeasurement::class, 'pme_indicator_measurement_id');
    }

    public function goalMeasurement(): BelongsTo
    {
        return $this->belongsTo(PmeStrategicGoalMeasurement::class, 'pme_goal_measurement_id');
    }

    public function reflectiveMonitoring(): BelongsTo
    {
        return $this->belongsTo(PmeReflectiveMonitoring::class, 'pme_reflective_monitoring_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
