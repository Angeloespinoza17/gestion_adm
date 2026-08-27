<?php

namespace App\Models\Remuneration;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RemunerationPaymentProposalItem extends RemunerationModel
{
    protected $casts = ['payment_amount' => 'integer', 'metadata' => 'array'];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(RemunerationPaymentProposal::class, 'proposal_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(RemunerationPaymentProposalAllocation::class, 'proposal_item_id');
    }
}
