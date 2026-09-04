<?php

namespace App\Models\HumanResources;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrDocumentControl extends HumanResourcesModel
{
    use SoftDeletes;

    protected $table = 'hr_document_controls';

    protected $casts = [
        'issued_at' => 'date:Y-m-d',
        'expires_at' => 'date:Y-m-d',
        'delivered_at' => 'datetime:Y-m-d H:i',
        'signed_at' => 'datetime:Y-m-d H:i',
        'alert_days' => 'integer',
        'file_size' => 'integer',
        'metadata' => 'array',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(HrDocumentRequirement::class, 'document_requirement_id');
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }
}
