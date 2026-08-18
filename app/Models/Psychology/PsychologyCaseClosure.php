<?php

namespace App\Models\Psychology;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsychologyCaseClosure extends Model
{
    protected $table = 'psychology_case_closures';

    protected $fillable = ['case_id', 'closure_type', 'reason', 'result_summary', 'recommendations', 'closed_at', 'closed_by', 'approved_by'];

    protected $casts = ['closed_at' => 'datetime'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
