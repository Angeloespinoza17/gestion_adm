<?php

namespace App\Models\Security;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'security_incident_id',
        'title',
        'message',
        'priority',
        'action_url',
        'read_at',
        'sent_via_mail_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'sent_via_mail_at' => 'datetime',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(SecurityIncident::class, 'security_incident_id');
    }
}
