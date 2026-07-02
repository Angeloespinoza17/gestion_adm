<?php

namespace App\Models\CentroApuntes;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CentroApuntesHistorialEstado extends Model
{
    use HasFactory;

    protected $table = 'centro_apuntes_historial_estados';

    protected $fillable = [
        'solicitud_id',
        'action_type',
        'previous_status',
        'new_status',
        'notes',
        'performed_by',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(CentroApuntesSolicitud::class, 'solicitud_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
