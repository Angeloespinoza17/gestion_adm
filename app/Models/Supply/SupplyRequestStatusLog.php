<?php

namespace App\Models\Supply;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplyRequestStatusLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['supply_request_id', 'from_status', 'to_status', 'note', 'changed_by'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(SupplyRequest::class, 'supply_request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
