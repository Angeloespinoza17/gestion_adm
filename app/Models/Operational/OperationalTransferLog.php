<?php

namespace App\Models\Operational;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalTransferLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['operational_transfer_request_id', 'user_id', 'action', 'old_status', 'new_status', 'details', 'created_at'];

    protected $casts = ['details' => 'array', 'created_at' => 'datetime'];

    public function transferRequest(): BelongsTo
    {
        return $this->belongsTo(OperationalTransferRequest::class, 'operational_transfer_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
