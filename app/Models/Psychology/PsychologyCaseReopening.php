<?php

namespace App\Models\Psychology;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsychologyCaseReopening extends Model
{
    protected $table = 'psychology_case_reopenings';

    protected $fillable = ['case_id', 'reason', 'reopened_at', 'reopened_by'];

    protected $casts = ['reopened_at' => 'datetime'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }
}
