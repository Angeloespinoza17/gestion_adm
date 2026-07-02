<?php

namespace App\Models\Pme;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmeChangeLog extends Model
{
    protected $table = 'pme_historial_cambios';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'action',
        'before_values',
        'after_values',
        'notes',
        'ip_address',
        'user_agent',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'before_values' => 'array',
        'after_values' => 'array',
        'changed_at' => 'datetime',
    ];

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
