<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PorterDailyLogEntry extends Model
{
    use HasFactory;

    public const CATEGORY_OPTIONS = [
        ['value' => 'novedad', 'label' => 'Novedad'],
        ['value' => 'incidencia', 'label' => 'Incidencia'],
        ['value' => 'visita', 'label' => 'Visita'],
        ['value' => 'proveedor', 'label' => 'Proveedor'],
        ['value' => 'llaves', 'label' => 'Llaves'],
        ['value' => 'observacion', 'label' => 'Observación'],
    ];

    public const PRIORITY_OPTIONS = [
        ['value' => 'baja', 'label' => 'Baja'],
        ['value' => 'media', 'label' => 'Media'],
        ['value' => 'alta', 'label' => 'Alta'],
    ];

    public const STATUS_OPTIONS = [
        ['value' => 'registrado', 'label' => 'Registrado'],
        ['value' => 'destacado', 'label' => 'Destacado'],
    ];

    protected $fillable = [
        'registered_by',
        'logged_on',
        'logged_at',
        'shift_label',
        'category',
        'priority',
        'status',
        'title',
        'detail',
        'metadata',
    ];

    protected $casts = [
        'logged_on' => 'date:Y-m-d',
        'logged_at' => 'datetime:Y-m-d H:i:s',
        'metadata' => 'array',
    ];

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function logs(): MorphMany
    {
        return $this->morphMany(PorterMovementLog::class, 'loggable')->latest('performed_at')->latest('id');
    }
}
