<?php

namespace App\Models\Convivencia;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ConvivenciaStatusLog extends Model
{
    use HasFactory;

    protected $table = 'convivencia_status_logs';

    protected $fillable = [
        'case_id',
        'changed_by',
        'previous_status',
        'new_status',
        'event_type',
        'comment',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaCase::class, 'case_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
