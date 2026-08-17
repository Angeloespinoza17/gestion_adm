<?php

namespace App\Models\Operational;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationalTransferQuote extends Model
{
    use HasFactory;

    protected $fillable = ['operational_transfer_request_id', 'provider_id', 'amount', 'valid_until', 'reference', 'notes', 'selected', 'created_by', 'updated_by'];

    protected $casts = ['amount' => 'integer', 'valid_until' => 'date:Y-m-d', 'selected' => 'boolean'];

    public function transferRequest(): BelongsTo
    {
        return $this->belongsTo(OperationalTransferRequest::class, 'operational_transfer_request_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(OperationalTransferProvider::class, 'provider_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(OperationalTransferDocument::class, 'quote_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
