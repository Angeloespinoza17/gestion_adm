<?php

namespace App\Models\Operational;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalTransferApproval extends Model
{
    use HasFactory;

    protected $fillable = ['operational_transfer_request_id', 'actor_user_id', 'step', 'decision', 'comments', 'internal_comments', 'acted_at'];

    protected $hidden = ['internal_comments'];

    protected $casts = ['acted_at' => 'datetime'];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }

    public function transferRequest(): BelongsTo
    {
        return $this->belongsTo(OperationalTransferRequest::class, 'operational_transfer_request_id');
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
