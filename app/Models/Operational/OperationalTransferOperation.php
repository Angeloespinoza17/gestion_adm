<?php

namespace App\Models\Operational;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalTransferOperation extends Model
{
    use HasFactory;

    protected $fillable = [
        'operational_transfer_request_id', 'selected_quote_id', 'provider_id', 'final_cost',
        'confirmation_reference', 'confirmation_notes', 'dte_number', 'dte_received_on',
        'payment_reference', 'payment_requested_on', 'payment_scheduled_on', 'paid_on',
        'administrative_notes', 'updated_by',
    ];

    protected $hidden = ['administrative_notes'];

    protected $casts = [
        'final_cost' => 'integer', 'dte_received_on' => 'date:Y-m-d',
        'payment_requested_on' => 'date:Y-m-d', 'payment_scheduled_on' => 'date:Y-m-d',
        'paid_on' => 'date:Y-m-d',
    ];

    public function transferRequest(): BelongsTo
    {
        return $this->belongsTo(OperationalTransferRequest::class, 'operational_transfer_request_id');
    }

    public function selectedQuote(): BelongsTo
    {
        return $this->belongsTo(OperationalTransferQuote::class, 'selected_quote_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(OperationalTransferProvider::class, 'provider_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
