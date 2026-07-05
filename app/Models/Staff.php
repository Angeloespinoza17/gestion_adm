<?php

namespace App\Models;

use App\Models\Security\SecurityShift;
use App\Models\Schedule\ScheduleEvent;
use App\Models\Schedule\TeacherContract;
use App\Models\Schedule\TeacherScheduleLayer;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use App\Models\Remuneration\RemunerationContractSetting;
use App\Models\Remuneration\RemunerationEmployeeProfile;
use App\Models\Remuneration\RemunerationPayroll;

class Staff extends Model
{
    use HasFactory;

    public const STATUS_OPTIONS = [
        ['value' => 'activo', 'label' => 'Activo'],
        ['value' => 'inactivo', 'label' => 'Inactivo'],
        ['value' => 'reemplazo', 'label' => 'Reemplazo'],
        ['value' => 'licencia', 'label' => 'Licencia'],
        ['value' => 'desvinculado', 'label' => 'Desvinculado'],
    ];

    public const CONTRACT_TYPE_OPTIONS = [
        ['value' => 'indefinido', 'label' => 'Indefinido'],
        ['value' => 'plazo_fijo', 'label' => 'Plazo fijo'],
        ['value' => 'honorarios', 'label' => 'Honorarios'],
        ['value' => 'reemplazo', 'label' => 'Reemplazo'],
        ['value' => 'part_time', 'label' => 'Part time'],
    ];

    public const WORKDAY_OPTIONS = [
        ['value' => 'completa', 'label' => 'Jornada completa'],
        ['value' => 'parcial', 'label' => 'Jornada parcial'],
        ['value' => 'por_horas', 'label' => 'Por horas'],
        ['value' => 'turnos', 'label' => 'Turnos'],
    ];

    public const MAINTENANCE_ROLE_OPTIONS = [
        ['value' => 'encargado_mantencion', 'label' => 'Encargado/a de mantención'],
        ['value' => 'auxiliar_mantenimiento', 'label' => 'Auxiliar de mantenimiento'],
        ['value' => 'auxiliar_aseo', 'label' => 'Auxiliar de aseo'],
        ['value' => 'apoyo_operativo', 'label' => 'Apoyo operativo'],
        ['value' => 'otro', 'label' => 'Otro'],
    ];

    protected $table = 'staff';

    protected $appends = [
        'profile_photo_url',
        'maintenance_role_label',
    ];

    protected $fillable = [
        'full_name',
        'rut',
        'birth_date',
        'institutional_email',
        'personal_email',
        'phone',
        'address',
        'commune',
        'region',
        'region_id',
        'commune_id',
        'cargo_id',
        'contract_type',
        'start_date',
        'end_date',
        'status',
        'workday',
        'contract_hours',
        'professional_title',
        'specialty',
        'professional_registration',
        'internal_notes',
        'profile_photo_path',
        'active',
        'can_receive_maintenance_orders',
        'maintenance_role',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'birth_date' => 'date:Y-m-d',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'contract_hours' => 'decimal:2',
        'active' => 'boolean',
        'can_receive_maintenance_orders' => 'boolean',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        if (!$this->profile_photo_path) {
            return null;
        }

        $url = Storage::disk('public')->url($this->profile_photo_path);
        $parts = parse_url((string) $url);

        if (is_array($parts) && isset($parts['path'])) {
            return $parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }

        return $url;
    }

    public function getMaintenanceRoleLabelAttribute(): ?string
    {
        if (!$this->maintenance_role) {
            return null;
        }

        $option = collect(self::MAINTENANCE_ROLE_OPTIONS)
            ->firstWhere('value', $this->maintenance_role);

        return $option['label'] ?? $this->maintenance_role;
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }

    public function regionRecord(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function communeRecord(): BelongsTo
    {
        return $this->belongsTo(Commune::class, 'commune_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'staff_id');
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class)->withTimestamps();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StaffDocument::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class)->orderByDesc('start_date')->orderByDesc('id');
    }

    public function remunerationProfile(): HasOne
    {
        return $this->hasOne(RemunerationEmployeeProfile::class);
    }

    public function remunerationContractSettings(): HasMany
    {
        return $this->hasMany(RemunerationContractSetting::class)->orderByDesc('effective_from')->orderByDesc('id');
    }

    public function remunerationPayrolls(): HasMany
    {
        return $this->hasMany(RemunerationPayroll::class)->orderByDesc('id');
    }

    public function permissionRequests(): HasMany
    {
        return $this->hasMany(PermissionRequest::class)->orderByDesc('created_at');
    }

    public function permissionWatchers(): HasMany
    {
        return $this->hasMany(StaffPermissionWatcher::class)->orderBy('id');
    }

    public function organigramRelations(): HasMany
    {
        return $this->hasMany(StaffOrganigramRelation::class)
            ->orderBy('relationship_type')
            ->orderByDesc('is_primary')
            ->orderBy('priority')
            ->orderBy('id');
    }

    public function subordinateOrganigramRelations(): HasMany
    {
        return $this->hasMany(StaffOrganigramRelation::class, 'related_staff_id')
            ->orderBy('relationship_type')
            ->orderByDesc('is_primary')
            ->orderBy('priority')
            ->orderBy('id');
    }

    public function dependencyReservations(): HasMany
    {
        return $this->hasMany(DependencyReservation::class)->orderByDesc('starts_at');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function securityShifts(): HasMany
    {
        return $this->hasMany(SecurityShift::class)->orderByDesc('scheduled_start_at');
    }

    public function teacherContracts(): HasMany
    {
        return $this->hasMany(TeacherContract::class)->orderByDesc('active')->orderByDesc('id');
    }

    public function scheduleLayers(): HasMany
    {
        return $this->hasMany(TeacherScheduleLayer::class)->orderBy('priority')->orderBy('name');
    }

    public function scheduleEvents(): HasMany
    {
        return $this->hasMany(ScheduleEvent::class)->orderBy('day_of_week')->orderBy('start_time');
    }

    public function porterGoodsMovements(): HasMany
    {
        return $this->hasMany(PorterGoodsMovement::class, 'responsible_staff_id')->orderByDesc('moved_at');
    }

    public function porterReceivedItems(): HasMany
    {
        return $this->hasMany(PorterReceivedItem::class)->orderByDesc('received_at');
    }
}
