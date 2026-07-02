<?php

namespace App\Models\Security;

use App\Models\MaintenanceDependency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SecurityRoundSector extends Model
{
    use HasFactory;

    public const STATE_OPTIONS = [
        ['value' => 'sin_novedad', 'label' => 'Sin novedad'],
        ['value' => 'observado', 'label' => 'Observado'],
        ['value' => 'acceso_restringido', 'label' => 'Acceso restringido'],
        ['value' => 'riesgo_detectado', 'label' => 'Riesgo detectado'],
        ['value' => 'incidente', 'label' => 'Incidente'],
    ];

    protected $fillable = [
        'security_round_id',
        'maintenance_dependency_id',
        'sector_name',
        'sector_state',
        'observations',
        'display_order',
    ];

    public function round(): BelongsTo
    {
        return $this->belongsTo(SecurityRound::class, 'security_round_id');
    }

    public function dependency(): BelongsTo
    {
        return $this->belongsTo(MaintenanceDependency::class, 'maintenance_dependency_id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(SecurityIncident::class)->latest('id');
    }
}
