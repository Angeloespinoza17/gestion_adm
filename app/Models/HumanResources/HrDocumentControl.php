<?php

namespace App\Models\HumanResources;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrDocumentControl extends HumanResourcesModel
{
    use SoftDeletes;

    protected $table = 'hr_document_controls';

    protected $casts = [
        'issued_at' => 'date:Y-m-d',
        'expires_at' => 'date:Y-m-d',
        'alert_days' => 'integer',
        'metadata' => 'array',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
