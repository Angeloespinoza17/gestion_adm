<?php

namespace App\Models\It;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItEquipmentStatusLog extends Model
{
    use HasFactory;

    protected $table = 'it_equipment_status_logs';

    protected $fillable = [
        'it_equipment_id',
        'previous_status',
        'new_status',
        'changed_at',
        'source_type',
        'source_id',
        'notes',
        'changed_by_user_id',
        'metadata',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(ItEquipment::class, 'it_equipment_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
