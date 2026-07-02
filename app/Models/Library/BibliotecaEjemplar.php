<?php

namespace App\Models\Library;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BibliotecaEjemplar extends Model
{
    use HasFactory;

    public const ORIGIN_OPTIONS = [
        'compra',
        'donacion',
        'reposicion',
        'traspaso',
        'inventario_inicial',
    ];

    public const STATE_OPTIONS = [
        'nuevo',
        'bueno',
        'regular',
        'danado',
        'en_reparacion',
        'perdido',
        'dado_de_baja',
    ];

    public const AVAILABILITY_OPTIONS = [
        'disponible',
        'prestado',
        'reservado',
        'en_reparacion',
        'danado',
        'perdido',
        'dado_de_baja',
    ];

    protected $table = 'biblioteca_ejemplares';

    protected $fillable = [
        'biblioteca_obra_id',
        'code',
        'barcode',
        'ingress_date',
        'origin',
        'estimated_value',
        'physical_location',
        'physical_state',
        'availability_status',
        'registered_by',
        'observations',
        'photo_urls',
        'last_inventory_checked_at',
        'is_active',
        'lost_at',
        'damaged_at',
        'withdrawn_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'ingress_date' => 'date:Y-m-d',
        'estimated_value' => 'decimal:2',
        'photo_urls' => 'array',
        'last_inventory_checked_at' => 'date:Y-m-d',
        'is_active' => 'boolean',
        'lost_at' => 'datetime',
        'damaged_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    public function obra(): BelongsTo
    {
        return $this->belongsTo(BibliotecaObra::class, 'biblioteca_obra_id');
    }

    public function prestamos(): HasMany
    {
        return $this->hasMany(BibliotecaPrestamo::class)->latest('borrowed_at');
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(BibliotecaReserva::class)->latest('requested_at');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(BibliotecaInventarioMovimiento::class)->latest('movement_date');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
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
