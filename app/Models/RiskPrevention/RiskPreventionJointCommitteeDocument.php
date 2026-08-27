<?php

namespace App\Models\RiskPrevention;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskPreventionJointCommitteeDocument extends Model
{
    public const TYPE_CONSTITUTION = 'constitucion';

    public const TYPE_MONTHLY_MINUTES = 'acta_mensual';

    protected $table = 'prevent_joint_committee_documents';

    protected $fillable = [
        'committee_id',
        'document_type',
        'period_key',
        'document_date',
        'title',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'notes',
        'uploaded_by',
    ];

    protected $hidden = [
        'file_path',
    ];

    protected $casts = [
        'document_date' => 'date:Y-m-d',
        'file_size' => 'integer',
    ];

    public function committee(): BelongsTo
    {
        return $this->belongsTo(RiskPreventionJointCommittee::class, 'committee_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
