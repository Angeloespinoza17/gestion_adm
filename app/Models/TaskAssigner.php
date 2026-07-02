<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAssigner extends Model
{
    use HasFactory;

    protected $fillable = [
        'target_user_id',
        'assigner_user_id',
        'created_by_user_id',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function assignerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigner_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
