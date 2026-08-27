<?php

namespace App\Models\PedagogicalManagement;

use App\Models\PedagogicalManagement\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PedagogicalInstrumentValidationEvent extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('El historial de resolución es append-only.'));
        static::deleting(fn () => throw new LogicException('El historial de resolución no se elimina.'));
    }

    protected function casts(): array
    {
        return ['before_snapshot' => 'array', 'after_snapshot' => 'array', 'performed_at' => 'datetime'];
    }

    public function validationResult(): BelongsTo
    {
        return $this->belongsTo(PedagogicalInstrumentValidationResult::class, 'validation_result_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
