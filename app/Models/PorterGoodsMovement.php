<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

class PorterGoodsMovement extends Model
{
    use HasFactory;

    public const MOVEMENT_TYPE_OPTIONS = [
        ['value' => 'recepcion_mercaderia', 'label' => 'Recepción de mercadería'],
        ['value' => 'entrega_mercaderia', 'label' => 'Entrega de mercadería'],
        ['value' => 'retiro_mercaderia', 'label' => 'Retiro de mercadería'],
    ];

    public const STATUS_OPTIONS = [
        ['value' => 'recibido_en_porteria', 'label' => 'Recibido en portería'],
        ['value' => 'derivado_a_departamento', 'label' => 'Derivado a departamento'],
        ['value' => 'entregado_a_responsable', 'label' => 'Entregado a responsable'],
        ['value' => 'pendiente', 'label' => 'Pendiente'],
        ['value' => 'rechazado', 'label' => 'Rechazado'],
    ];

    public const DOCUMENT_TYPE_OPTIONS = [
        ['value' => 'guia_despacho', 'label' => 'Guía de despacho'],
        ['value' => 'factura', 'label' => 'Factura'],
        ['value' => 'orden_compra', 'label' => 'Orden de compra'],
        ['value' => 'boleta', 'label' => 'Boleta'],
        ['value' => 'otro', 'label' => 'Otro'],
    ];

    protected $fillable = [
        'movement_type',
        'department_id',
        'responsible_staff_id',
        'registered_by',
        'delivered_by',
        'status',
        'moved_at',
        'delivered_at',
        'contact_name',
        'contact_rut',
        'company',
        'phone',
        'vehicle_plate',
        'goods_detail',
        'quantity',
        'unit',
        'document_type',
        'document_number',
        'observations',
        'received_by_name',
        'received_by_identifier',
        'delivery_observations',
        'attachment_path',
        'attachment_original_name',
        'attachment_mime_type',
        'metadata',
    ];

    protected $casts = [
        'moved_at' => 'datetime:Y-m-d H:i:s',
        'delivered_at' => 'datetime:Y-m-d H:i:s',
        'quantity' => 'decimal:2',
        'metadata' => 'array',
    ];

    protected $appends = [
        'attachment_url',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function responsibleStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_staff_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function authorizationRequests(): MorphMany
    {
        return $this->morphMany(PorterAuthorizationRequest::class, 'authorizable')->latest('id');
    }

    public function logs(): MorphMany
    {
        return $this->morphMany(PorterMovementLog::class, 'loggable')->latest('performed_at')->latest('id');
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (!$this->attachment_path) {
            return null;
        }

        $url = Storage::disk('public')->url($this->attachment_path);
        $parts = parse_url((string) $url);

        if (is_array($parts) && isset($parts['path'])) {
            return $parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }

        return $url;
    }
}
