<?php

namespace App\Models\Infirmary;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfirmaryAttentionCall extends Model
{
    use HasFactory;

    public const STATUS_OPTIONS = [
        ['value' => 'pendiente', 'label' => 'Pendiente'],
        ['value' => 'contesto', 'label' => 'Contestó'],
        ['value' => 'no_contesto', 'label' => 'No contestó'],
        ['value' => 'mensaje_dejado', 'label' => 'Mensaje dejado'],
    ];

    protected $table = 'infirmary_attention_calls';

    protected $fillable = [
        'student_profile_id',
        'attention_id',
        'called_at',
        'person_contacted',
        'relationship',
        'phone_number',
        'call_status',
        'reason',
        'conversation_summary',
        'commitments',
        'estimated_arrival_at',
        'duration_minutes',
        'called_by_user_id',
    ];

    protected $casts = [
        'called_at' => 'datetime:Y-m-d H:i:s',
        'estimated_arrival_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function attention(): BelongsTo
    {
        return $this->belongsTo(InfirmaryAttention::class, 'attention_id');
    }

    public function calledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'called_by_user_id');
    }
}
