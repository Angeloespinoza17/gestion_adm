<?php

namespace App\Models\Pme;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmeGeneratedReport extends Model
{
    protected $table = 'pme_reportes_generados';

    protected $fillable = [
        'pme_plan_id',
        'report_type',
        'title',
        'filters',
        'summary',
        'format',
        'rows_count',
        'state',
        'generated_by',
        'generated_at',
        'file_path',
        'observations',
    ];

    protected $casts = [
        'filters' => 'array',
        'summary' => 'array',
        'rows_count' => 'integer',
        'generated_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PmePlan::class, 'pme_plan_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
