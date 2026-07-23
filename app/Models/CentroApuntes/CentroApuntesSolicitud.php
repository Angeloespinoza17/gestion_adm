<?php

namespace App\Models\CentroApuntes;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CentroApuntesSolicitud extends Model
{
    use HasFactory;

    public const TASK_TYPES = [
        'guia',
        'evaluacion',
        'pauta_de_evaluacion',
        'actividad_en_clases',
        'idps',
        'otro',
    ];

    public const PAPER_SIZES = [
        'carta',
        'oficio',
    ];

    public const PRIORITY_OPTIONS = [
        'normal',
        'urgente',
        'entrega_inmediata',
    ];

    public const STATUS_OPTIONS = [
        'pendiente',
        'recibida',
        'en_proceso',
        'pausada',
        'lista_para_retiro',
        'entregada',
        'rechazada',
        'anulada',
    ];

    protected $table = 'centro_apuntes_solicitudes';

    protected $hidden = [
        'estimated_cost_per_sheet',
        'estimated_cost_per_copy',
        'estimated_cost_total',
    ];

    protected $fillable = [
        'request_code',
        'requested_by_user_id',
        'requested_by_name_snapshot',
        'subject_id',
        'subject_name_snapshot',
        'machine_id',
        'machine_name_snapshot',
        'task_type',
        'task_type_other',
        'requested_at',
        'delivery_date',
        'sheet_count',
        'copies_count',
        'paper_size',
        'priority',
        'is_urgent',
        'is_immediate',
        'instructions',
        'observations',
        'internal_observations',
        'status',
        'received_by_user_id',
        'received_by_name_snapshot',
        'delivered_at',
        'status_changed_at',
        'estimated_total_impressions',
        'estimated_cost_per_sheet',
        'estimated_cost_per_copy',
        'estimated_cost_total',
        'attachment_count',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'requested_at' => 'datetime:Y-m-d H:i',
        'delivery_date' => 'date:Y-m-d',
        'delivered_at' => 'datetime:Y-m-d H:i',
        'status_changed_at' => 'datetime:Y-m-d H:i',
        'is_urgent' => 'boolean',
        'is_immediate' => 'boolean',
        'estimated_cost_per_sheet' => 'decimal:2',
        'estimated_cost_per_copy' => 'decimal:2',
        'estimated_cost_total' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(CentroApuntesAsignatura::class, 'subject_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(CentroApuntesMaquina::class, 'machine_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CentroApuntesAdjunto::class, 'solicitud_id')->latest('id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(CentroApuntesHistorialEstado::class, 'solicitud_id')->latest('id');
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
