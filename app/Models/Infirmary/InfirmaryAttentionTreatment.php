<?php

namespace App\Models\Infirmary;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfirmaryAttentionTreatment extends Model
{
    use HasFactory;

    public const TYPE_OPTIONS = [
        'compresa_fria',
        'compresa_caliente',
        'curaciones',
        'administracion_medicamento',
        'reposo',
        'lavado_heridas',
        'vendaje',
        'inmovilizacion',
        'elevacion_extremidad',
        'aplicacion_hielo',
        'toma_temperatura',
        'control_glicemia',
        'otro',
    ];

    protected $table = 'infirmary_attention_treatments';

    protected $fillable = [
        'attention_id',
        'treatment_types',
        'treatment_other',
        'medication_id',
        'medication_quantity',
        'blood_pressure',
        'pulse',
        'respiratory_rate',
        'temperature',
        'oxygen_saturation',
        'weight',
        'height',
        'bmi',
        'vital_signs_notes',
        'emotional_support_required',
        'emotional_comment',
        'emotional_support_type',
        'emotional_duration_minutes',
        'emotional_professional_id',
        'other_treatments',
        'notes',
    ];

    protected $casts = [
        'treatment_types' => 'array',
        'medication_quantity' => 'decimal:2',
        'temperature' => 'decimal:1',
        'weight' => 'decimal:2',
        'height' => 'decimal:2',
        'bmi' => 'decimal:2',
        'emotional_support_required' => 'boolean',
    ];

    public function attention(): BelongsTo
    {
        return $this->belongsTo(InfirmaryAttention::class, 'attention_id');
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(InfirmaryMedication::class, 'medication_id');
    }

    public function emotionalProfessional(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'emotional_professional_id');
    }
}
