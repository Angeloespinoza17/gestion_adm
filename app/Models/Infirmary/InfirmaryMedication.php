<?php

namespace App\Models\Infirmary;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InfirmaryMedication extends Model
{
    use HasFactory;

    public const STATUS_DISPONIBLE = 'disponible';
    public const STATUS_STOCK_BAJO = 'stock_bajo';
    public const STATUS_AGOTADO = 'agotado';
    public const STATUS_PROXIMO_VENCER = 'proximo_a_vencer';
    public const STATUS_VENCIDO = 'vencido';

    protected $table = 'infirmary_medications';

    protected $fillable = [
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
        'observations',
        'status',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'manufactured_at' => 'date:Y-m-d',
        'expires_at' => 'date:Y-m-d',
        'current_stock' => 'decimal:2',
        'minimum_stock' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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
