<?php

namespace App\Models\Remuneration;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RemunerationPeriod extends RemunerationModel
{
    use SoftDeletes;

    public const CLOSED_STATUSES = ['cerrado'];

    protected $casts = [
        'period_start' => 'date:Y-m-d',
        'period_end' => 'date:Y-m-d',
        'closed_at' => 'datetime:Y-m-d H:i',
        'reopened_at' => 'datetime:Y-m-d H:i',
    ];

    public function payrolls(): HasMany
    {
        return $this->hasMany(RemunerationPayroll::class, 'period_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(RemunerationMovement::class, 'period_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED_STATUSES, true);
    }
}
