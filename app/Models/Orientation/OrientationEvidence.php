<?php

namespace App\Models\Orientation;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrientationEvidence extends Model
{
    use HasFactory;

    public const TYPES = ['attendance', 'photograph', 'minutes', 'report', 'authorization', 'survey', 'planning', 'other'];

    protected $table = 'orientation_evidences';

    protected $fillable = [
        'orientation_action_id',
        'orientation_activity_id',
        'evidence_type',
        'title',
        'description',
        'occurred_on',
        'storage_disk',
        'file_path',
        'external_url',
        'original_name',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    protected $casts = [
        'occurred_on' => 'date:Y-m-d',
        'size_bytes' => 'integer',
    ];

    public function action(): BelongsTo
    {
        return $this->belongsTo(OrientationAction::class, 'orientation_action_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(OrientationActivity::class, 'orientation_activity_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
