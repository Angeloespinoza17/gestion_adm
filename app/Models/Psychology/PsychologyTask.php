<?php

namespace App\Models\Psychology;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PsychologyTask extends Model
{
    use SoftDeletes;

    protected $table = 'psychology_tasks';

    protected $fillable = ['case_id', 'activity_id', 'responsible_user_id', 'title', 'description', 'type', 'priority', 'status', 'due_at', 'remind_at', 'completed_at', 'completion_evidence', 'created_by', 'updated_by'];

    protected $casts = ['due_at' => 'datetime', 'remind_at' => 'datetime', 'completed_at' => 'datetime'];

    public function case(): BelongsTo
    {
        return $this->belongsTo(PsychologyCase::class, 'case_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}
