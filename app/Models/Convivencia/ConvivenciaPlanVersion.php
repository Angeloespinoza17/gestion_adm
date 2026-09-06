<?php

namespace App\Models\Convivencia;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConvivenciaPlanVersion extends Model
{
    use HasFactory;

    protected $table = 'convivencia_plan_versions';

    protected $fillable = [
        'plan_id',
        'version_number',
        'change_summary',
        'snapshot',
        'created_by',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'snapshot' => 'array',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaPlan::class, 'plan_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
