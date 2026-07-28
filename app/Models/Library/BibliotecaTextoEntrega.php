<?php

namespace App\Models\Library;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BibliotecaTextoEntrega extends Model
{
    public const STATUSES = ['pendiente', 'parcial', 'entregado'];

    protected $table = 'biblioteca_texto_entregas';

    protected $fillable = [
        'biblioteca_texto_orden_id',
        'student_profile_id',
        'student_name_snapshot',
        'student_rut_snapshot',
        'status',
        'delivered_at',
        'signature_data',
        'signature_name',
        'signature_rut',
        'signed_at',
        'pending_reason',
        'notes',
        'delivered_by_user_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(BibliotecaTextoOrden::class, 'biblioteca_texto_orden_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BibliotecaTextoEntregaItem::class, 'biblioteca_texto_entrega_id');
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by_user_id');
    }
}
