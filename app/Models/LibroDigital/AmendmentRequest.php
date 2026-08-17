<?php

namespace App\Models\LibroDigital;

use App\Enums\LibroDigital\AmendmentStatus;
use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AmendmentRequest extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_amendment_requests';

    protected function casts(): array
    {
        return [
            'status' => AmendmentStatus::class,
            'before_snapshot' => 'array',
            'proposed_snapshot' => 'array',
            'evidence' => 'array',
            'requires_signature' => 'boolean',
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function amendable(): MorphTo
    {
        return $this->morphTo();
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(AmendmentApproval::class);
    }
}
