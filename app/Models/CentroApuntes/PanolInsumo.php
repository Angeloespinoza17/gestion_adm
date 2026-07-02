<?php

namespace App\Models\CentroApuntes;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class PanolInsumo extends Model
{
    use HasFactory;

    public const CATEGORY_OPTIONS = [
        'papel',
        'tinta',
        'toner',
        'espirales',
        'micas',
        'tapas',
        'contratapas',
        'corchetes',
        'carpetas',
        'plumones',
        'lapices',
        'cartulinas',
        'material_de_oficina',
        'material_pedagogico',
        'otro',
    ];

    public const UNIT_OPTIONS = [
        'unidad',
        'paquete',
        'caja',
        'resma',
        'litro',
        'metro',
        'set',
        'rollo',
    ];

    public const STATUS_OPTIONS = [
        'disponible',
        'stock_bajo',
        'agotado',
        'vencido',
        'dado_de_baja',
    ];

    protected $table = 'panol_insumos';

    protected $appends = [
        'photo_url',
    ];

    protected $fillable = [
        'name',
        'category',
        'unit_of_measure',
        'current_stock',
        'minimum_stock',
        'maximum_stock',
        'location',
        'supplier_id',
        'unit_price_estimated',
        'last_purchase_at',
        'expires_at',
        'status',
        'observations',
        'photo_path',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'current_stock' => 'decimal:2',
        'minimum_stock' => 'decimal:2',
        'maximum_stock' => 'decimal:2',
        'unit_price_estimated' => 'decimal:2',
        'last_purchase_at' => 'date:Y-m-d',
        'expires_at' => 'date:Y-m-d',
        'active' => 'boolean',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(PanolMovimiento::class, 'insumo_id')->latest('moved_at');
    }

    public function deliveryDetails(): HasMany
    {
        return $this->hasMany(PanolEntregaDetalle::class, 'insumo_id')->latest('id');
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
