<?php

namespace App\Models;

use App\Models\Security\SecurityIncident;
use App\Models\Security\SecurityRoundSector;
use App\Models\Security\SecurityShift;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class MaintenanceDependency extends Model
{
    use HasFactory;

    public const KIND_SPACE = 'space';
    public const KIND_TECHNICAL_ASSET = 'technical_asset';

    public const AVAILABILITY_AVAILABLE = 'disponible';
    public const AVAILABILITY_UNAVAILABLE = 'no_disponible';
    public const AVAILABILITY_MAINTENANCE = 'mantencion';
    public const AVAILABILITY_BLOCKED = 'bloqueada';

    protected $appends = [
        'image_url',
    ];

    protected $fillable = [
        'dependency_kind',
        'parent_dependency_id',
        'dependency_type_id',
        'code',
        'name',
        'description',
        'location',
        'floor_sector',
        'capacity_max',
        'available_equipment',
        'availability_status',
        'distribution',
        'sector',
        'zone',
        'usage',
        'distribution_code',
        'floor_code',
        'dependency_code',
        'numbering',
        'active',
        'responsible_staff_id',
        'notes',
        'observations',
        'image_path',
        'calendar_color',
        'requires_approval',
        'is_reservable',
        'is_inventory_auditable',
        'is_maintenance_location',
    ];

    protected $casts = [
        'active' => 'boolean',
        'numbering' => 'integer',
        'capacity_max' => 'integer',
        'requires_approval' => 'boolean',
        'is_reservable' => 'boolean',
        'is_inventory_auditable' => 'boolean',
        'is_maintenance_location' => 'boolean',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        $url = Storage::disk('public')->url($this->image_path);
        $parts = parse_url((string) $url);

        if (is_array($parts) && isset($parts['path'])) {
            return $parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }

        return $url;
    }

    public function scopePhysicalSpaces($query)
    {
        return $query->where('dependency_kind', self::KIND_SPACE);
    }

    public function scopeTechnicalAssets($query)
    {
        return $query->where('dependency_kind', self::KIND_TECHNICAL_ASSET);
    }

    public function scopeReservableSpaces($query)
    {
        return $query
            ->physicalSpaces()
            ->where('is_reservable', true);
    }

    public function scopeInventoryAuditableSpaces($query)
    {
        return $query
            ->physicalSpaces()
            ->where('is_inventory_auditable', true);
    }

    public function scopeMaintenanceLocations($query)
    {
        return $query
            ->physicalSpaces()
            ->where('is_maintenance_location', true);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(DependencyType::class, 'dependency_type_id');
    }

    public function parentDependency(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_dependency_id');
    }

    public function technicalAreas(): HasMany
    {
        return $this->hasMany(self::class, 'parent_dependency_id');
    }

    public function responsibleStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_staff_id');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(MaintenanceWorkOrder::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'dependency_id');
    }

    public function inventoryAudits(): HasMany
    {
        return $this->hasMany(InventoryDependencyAudit::class, 'maintenance_dependency_id');
    }

    public function latestInventoryAudit(): HasOne
    {
        return $this->hasOne(InventoryDependencyAudit::class, 'maintenance_dependency_id')
            ->latestOfMany('audited_at');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(DependencyReservation::class, 'maintenance_dependency_id');
    }

    public function approvers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'maintenance_dependency_approvers',
            'maintenance_dependency_id',
            'user_id'
        )->withTimestamps();
    }

    public function securityShifts(): HasMany
    {
        return $this->hasMany(SecurityShift::class, 'maintenance_dependency_id');
    }

    public function securityRoundSectors(): HasMany
    {
        return $this->hasMany(SecurityRoundSector::class, 'maintenance_dependency_id');
    }

    public function securityIncidents(): HasMany
    {
        return $this->hasMany(SecurityIncident::class, 'maintenance_dependency_id');
    }
}
