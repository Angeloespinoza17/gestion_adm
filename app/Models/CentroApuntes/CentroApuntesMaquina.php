<?php

namespace App\Models\CentroApuntes;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CentroApuntesMaquina extends Model
{
    use HasFactory;

    public const TYPE_OPTIONS = [
        'impresora',
        'fotocopiadora',
        'riso',
        'multifuncional',
        'guillotina',
        'anilladora',
        'plastificadora',
        'escaner',
        'otra',
    ];

    public const STATUS_OPTIONS = [
        'activa',
        'inactiva',
        'en_mantencion',
        'danada',
    ];

    protected $table = 'centro_apuntes_maquinas';

    protected $hidden = [
        'estimated_cost_letter',
        'estimated_cost_officio',
    ];

    protected $fillable = [
        'name',
        'internal_code',
        'type',
        'brand',
        'model',
        'location',
        'responsible_user_id',
        'status',
        'estimated_cost_letter',
        'estimated_cost_officio',
        'observations',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'estimated_cost_letter' => 'decimal:2',
        'estimated_cost_officio' => 'decimal:2',
    ];

    public function solicitudes(): HasMany
    {
        return $this->hasMany(CentroApuntesSolicitud::class, 'machine_id')->latest('requested_at');
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
