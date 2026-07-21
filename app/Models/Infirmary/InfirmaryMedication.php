<?php

namespace App\Models\Infirmary;

use App\Models\StudentProfile;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InfirmaryMedication extends Model
{
    use HasFactory;

    public const INVENTORY_TYPE_MEDICATION = 'medication';
    public const INVENTORY_TYPE_SUPPLY = 'supply';

    public const SOURCE_SCHOOL = 'school';
    public const SOURCE_GUARDIAN = 'guardian';

    public const INVENTORY_TYPE_OPTIONS = [
        ['value' => self::INVENTORY_TYPE_SUPPLY, 'label' => 'Insumo general'],
        ['value' => self::INVENTORY_TYPE_MEDICATION, 'label' => 'Medicamento'],
    ];

    public const SOURCE_TYPE_OPTIONS = [
        ['value' => self::SOURCE_SCHOOL, 'label' => 'Stock del colegio'],
        ['value' => self::SOURCE_GUARDIAN, 'label' => 'Entregado por apoderado'],
    ];

    public const STATUS_DISPONIBLE = 'disponible';
    public const STATUS_STOCK_BAJO = 'stock_bajo';
    public const STATUS_AGOTADO = 'agotado';
    public const STATUS_PROXIMO_VENCER = 'proximo_a_vencer';
    public const STATUS_VENCIDO = 'vencido';

    protected $table = 'infirmary_medications';

    protected $fillable = [
        'inventory_type',
        'source_type',
        'name',
        'commercial_name',
        'active_ingredient',
        'presentation',
        'concentration',
        'unit',
        'laboratory',
        'current_stock',
        'minimum_stock',
        'physical_location',
        'batch',
        'manufactured_at',
        'expires_at',
        'supplier_id',
        'student_profile_id',
        'received_from_guardian',
        'received_at',
        'observations',
        'status',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'manufactured_at' => 'date:Y-m-d',
        'expires_at' => 'date:Y-m-d',
        'received_at' => 'datetime',
        'current_stock' => 'decimal:2',
        'minimum_stock' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InfirmaryMedicationMovement::class, 'medication_id')->latest('moved_at')->latest('id');
    }

    public function authorizations(): HasMany
    {
        return $this->hasMany(InfirmaryMedicationAuthorization::class, 'medication_id')->latest('start_date')->latest('id');
    }

    public function administrations(): HasMany
    {
        return $this->hasMany(InfirmaryMedicationAdministration::class, 'medication_id')->latest('administered_at')->latest('id');
    }
}
