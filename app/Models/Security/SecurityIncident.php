<?php

namespace App\Models\Security;

use App\Models\InventoryItem;
use App\Models\MaintenanceDependency;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SecurityIncident extends Model
{
    use HasFactory;

    public const PRIORITY_BAJA = 'baja';
    public const PRIORITY_MEDIA = 'media';
    public const PRIORITY_ALTA = 'alta';
    public const PRIORITY_CRITICA = 'critica';

    public const PRIORITY_OPTIONS = [
        ['value' => self::PRIORITY_BAJA, 'label' => 'Baja'],
        ['value' => self::PRIORITY_MEDIA, 'label' => 'Media'],
        ['value' => self::PRIORITY_ALTA, 'label' => 'Alta'],
        ['value' => self::PRIORITY_CRITICA, 'label' => 'Urgente / crítica'],
    ];

    protected $fillable = [
        'security_shift_id',
        'security_round_id',
        'security_round_sector_id',
        'reported_by_user_id',
        'status_id',
        'maintenance_dependency_id',
        'inventory_item_id',
        'current_responsible_user_id',
        'priority',
        'title',
        'description',
        'sector_name',
        'requires_immediate_attention',
        'response_due_at',
        'responded_at',
        'resolved_at',
        'alert_sent_at',
        'response_summary',
        'closure_evidence_notes',
    ];

    protected $casts = [
        'requires_immediate_attention' => 'boolean',
        'response_due_at' => 'datetime',
        'responded_at' => 'datetime',
        'resolved_at' => 'datetime',
        'alert_sent_at' => 'datetime',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(SecurityShift::class, 'security_shift_id');
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(SecurityRound::class, 'security_round_id');
    }

    public function roundSector(): BelongsTo
    {
        return $this->belongsTo(SecurityRoundSector::class, 'security_round_sector_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(SecurityIncidentStatus::class, 'status_id');
    }

    public function dependency(): BelongsTo
    {
        return $this->belongsTo(MaintenanceDependency::class, 'maintenance_dependency_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function currentResponsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_responsible_user_id');
    }

    public function evidences(): MorphMany
    {
        return $this->morphMany(SecurityEvidence::class, 'attachable')->latest('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SecurityIncidentComment::class)->latest('id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SecurityIncidentAssignment::class)->latest('id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(SecurityNotification::class)->latest('id');
    }
}
