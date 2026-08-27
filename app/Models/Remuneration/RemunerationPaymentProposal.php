<?php

namespace App\Models\Remuneration;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\LibroDigital\School;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RemunerationPaymentProposal extends RemunerationModel
{
    use HasPublicUlid;

    protected $casts = [
        'version' => 'integer',
        'net_total' => 'integer',
        'distributed_total' => 'integer',
        'employer_contribution_total' => 'integer',
        'total_cost' => 'integer',
        'confirmed_at' => 'datetime:Y-m-d H:i',
        'summary' => 'array',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(RemunerationPeriod::class, 'period_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RemunerationPaymentProposalItem::class, 'proposal_id');
    }
}
