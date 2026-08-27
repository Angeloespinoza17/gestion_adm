<?php

namespace App\Models\Remuneration;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemunerationPayslipIssue extends RemunerationModel
{
    protected $casts = ['context' => 'array', 'resolved_at' => 'datetime:Y-m-d H:i'];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(RemunerationPayslipBatch::class, 'batch_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
