<?php

namespace App\Models\Library;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BibliotecaInventarioMovimiento extends Model
{
    use HasFactory;

    public const TYPE_OPTIONS = [
        'alta',
        'baja',
        'cambio_ubicacion',
        'cambio_estado',
        'prestamo',
        'devolucion',
        'reserva',
        'mora',
        'danio',
        'perdida',
        'inventario_fisico',
        'reparacion',
        'ajuste',
    ];

    protected $table = 'biblioteca_inventario_movimientos';

    protected $fillable = [
        'biblioteca_ejemplar_id',
        'movement_type',
        'previous_location',
        'new_location',
        'previous_state',
        'new_state',
        'movement_date',
        'physical_count_status',
        'notes',
        'responsible_user_id',
        'metadata',
    ];

    protected $casts = [
        'movement_date' => 'datetime',
        'metadata' => 'array',
    ];

    public function ejemplar(): BelongsTo
    {
        return $this->belongsTo(BibliotecaEjemplar::class, 'biblioteca_ejemplar_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}
