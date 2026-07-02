<?php

namespace App\Models\ApoyoProfesional;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApoyoPlanAccion extends Model
{
    use HasFactory;

    protected $table = 'apoyo_plan_acciones';

    protected $fillable = [
        'plan_id',
        'action_description',
        'responsible_label',
        'due_date',
        'completed_at',
        'status',
        'observations',
    ];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'completed_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ApoyoPlan::class, 'plan_id');
    }
}
