<?php

namespace App\Models\Security;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityIncidentComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'security_incident_id',
        'user_id',
        'status_id',
        'assigned_to_user_id',
        'comment',
        'responded_at',
        'is_internal',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
        'is_internal' => 'boolean',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(SecurityIncident::class, 'security_incident_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(SecurityIncidentStatus::class, 'status_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}
