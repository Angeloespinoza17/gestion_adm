<?php

namespace App\Models\It;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class ItEquipment extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const TYPE_OPTIONS = [
        'notebook',
        'desktop',
        'tablet',
        'projector',
        'printer',
        'router',
        'switch',
        'monitor',
        'keyboard',
        'mouse',
        'speaker',
        'audio_equipment',
        'camera',
        'other',
    ];

    public const STATUS_OPTIONS = [
        'disponible',
        'prestado',
        'en_mantencion',
        'danado',
        'dado_de_baja',
    ];

    protected $table = 'it_equipment';

    protected $appends = [
        'photo_url',
    ];

    protected $fillable = [
        'internal_code',
        'equipment_type',
        'brand',
        'model',
        'serial_number',
        'status',
        'location_name',
        'responsible_user_id',
        'responsible_name',
        'acquisition_date',
        'reference_value',
        'observations',
        'photo_path',
        'photo_original_name',
        'photo_mime_type',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'acquisition_date' => 'date:Y-m-d',
        'reference_value' => 'decimal:2',
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo_path) {
            return null;
        }

        $url = Storage::disk('public')->url($this->photo_path);
        $parts = parse_url((string) $url);

        if (is_array($parts) && isset($parts['path'])) {
            return $parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }

        return $url;
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'disponible')->where('active', true);
    }

    public function scopeLoaned($query)
    {
        return $query->where('status', 'prestado')->where('active', true);
    }

    public function scopeUnderMaintenance($query)
    {
        return $query->where('status', 'en_mantencion')->where('active', true);
    }

    public function scopeDamaged($query)
    {
        return $query->where('status', 'danado')->where('active', true);
    }

    public function scopeDecommissioned($query)
    {
        return $query->where('status', 'dado_de_baja');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(ItEquipmentLoan::class, 'it_equipment_id')->latest('borrowed_at');
    }

    public function maintenanceReports(): HasMany
    {
        return $this->hasMany(ItEquipmentMaintenanceReport::class, 'it_equipment_id')->latest('maintenance_date');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ItEquipmentStatusLog::class, 'it_equipment_id')->latest('changed_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ItEquipmentAttachment::class, 'it_equipment_id')->latest('id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
