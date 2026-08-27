<?php

namespace App\Models\Remuneration;

class RemunerationPaymentProposalAllocation extends RemunerationModel
{
    protected $casts = ['amount' => 'integer', 'source_detail' => 'array'];
}
