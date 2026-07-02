<?php

namespace App\Models\Infirmary;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfirmaryAttentionFollowUp extends Model
{
    use HasFactory;

    public const STATUS_OPTIONS = [
        ['value' => 'pendiente', 'label' => 'Pendiente'],
        ['value' => 'en_proceso', 'label' => 'En proceso'],
        ['value' => 'cerrado', 'label' => 'Cerrado'],
    ];

    protected $table = 'infirmary_attention_follow_ups';

    protected $fillable = [
        'attention_id',
        'followed_at',
        'responsible_user_id',
        'comment',
        'status',
        'next_review_at',
        'completed_at',
    ];

    protected $casts = [
        'followed_at' => 'datetime:Y-m-d H:i:s',
        'next_review_at' => 'datetime:Y-m-d H:i:s',
        'completed_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function attention(): BelongsTo
    {
        return $this->belongsTo(InfirmaryAttention::class, 'attention_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}
