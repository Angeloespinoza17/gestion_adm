<?php

namespace App\Models\Security;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityIncidentAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'security_incident_id',
        'user_id',
        'assigned_by_user_id',
        'assigned_at',
        'released_at',
        'is_current',
        'notes',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'released_at' => 'datetime',
        'is_current' => 'boolean',
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

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
