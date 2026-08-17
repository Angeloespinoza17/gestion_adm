<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmendmentApproval extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_amendment_approvals';

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function amendmentRequest(): BelongsTo
    {
        return $this->belongsTo(AmendmentRequest::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
