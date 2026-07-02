<?php

namespace App\Models\Pme;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmeSepIncome extends Model
{
    use SoftDeletes;

    protected $table = 'pme_ingresos_sep';

    protected $fillable = [
        'pme_plan_id',
        'school_year',
        'month',
        'income_type',
        'estimated_amount',
        'received_amount',
        'received_at',
        'bank_account',
        'supporting_document_path',
        'supporting_document_name',
        'observations',
        'state',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'school_year' => 'integer',
        'month' => 'integer',
        'estimated_amount' => 'decimal:2',
        'received_amount' => 'decimal:2',
        'received_at' => 'date:Y-m-d',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PmePlan::class, 'pme_plan_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
