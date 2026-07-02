<?php

namespace App\Models\Infirmary;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfirmaryAttentionReferral extends Model
{
    use HasFactory;

    public const TYPE_OPTIONS = [
        'regresa_a_sala',
        'observacion_en_enfermeria',
        'retiro_por_apoderado',
        'se_retira_a_domicilio',
        'derivacion_a_inspectoria',
        'derivacion_a_convivencia_escolar',
        'derivacion_a_psicologa',
        'derivacion_a_trabajadora_social',
        'derivacion_a_pie',
        'derivacion_a_direccion',
        'cesfam',
        'sapu',
        'urgencias',
        'hospital',
        'clinica',
        'samu',
        'ambulancia_privada',
    ];

    protected $table = 'infirmary_attention_referrals';

    protected $fillable = [
        'attention_id',
        'referral_type',
        'referred_at',
        'responsible_user_id',
        'responsible_name',
        'reason',
        'observations',
        'result',
    ];

    protected $casts = [
        'referred_at' => 'datetime:Y-m-d H:i:s',
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
