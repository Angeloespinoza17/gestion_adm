<?php

namespace App\Models\Convivencia;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConvivenciaCaseFollowUp extends Model
{
    use HasFactory;

    protected $table = 'convivencia_case_followups';

    protected $fillable = [
        'case_id',
        'responsible_user_id',
        'follow_up_at',
        'entry_type',
        'status',
        'title',
        'notes',
        'next_follow_up_at',
    ];

    protected $casts = [
        'follow_up_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaCase::class, 'case_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}
