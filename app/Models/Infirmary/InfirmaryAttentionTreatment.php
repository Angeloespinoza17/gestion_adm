<?php

namespace App\Models\Infirmary;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfirmaryAttentionTreatment extends Model
{
    use HasFactory;

    public const CATEGORY_OPTIONS = [
        ['value' => 'fisico', 'label' => 'Físico'],
        ['value' => 'emocional', 'label' => 'Emocional'],
        ['value' => 'derivacion', 'label' => 'Derivación'],
        ['value' => 'csv', 'label' => 'CSV'],
        ['value' => 'otro', 'label' => 'OTRO'],
    ];

    public const TYPE_OPTIONS = [
        'compresa_fria',
        'compresa_caliente',
        'medicamento_sos',
        'curaciones',
        'administracion_medicamento',
        'apoyo_equipo_formacion',
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

    public const PHYSICAL_TYPE_OPTIONS = [
        ['value' => 'compresa_fria', 'label' => 'Compresa fría'],
        ['value' => 'compresa_caliente', 'label' => 'Compresa de calor'],
        ['value' => 'administracion_medicamento', 'label' => 'Administración de medicamento'],
        ['value' => 'medicamento_sos', 'label' => 'Medicamento S.O.S.'],
        ['value' => 'apoyo_equipo_formacion', 'label' => 'Apoyo equipo formación'],
        ['value' => 'curaciones', 'label' => 'Curaciones'],
    ];

    public const DERIVATION_TYPE_OPTIONS = [
        ['value' => 'sala', 'label' => 'Sala'],
        ['value' => 'domicilio', 'label' => 'Domicilio'],
        ['value' => 'samu', 'label' => 'SAMU'],
        ['value' => 'urgencias', 'label' => 'Urgencias'],
    ];

    public const DERIVATION_SUPPORT_TEAM_OPTIONS = [
        ['value' => 'equipo_directivo', 'label' => 'Equipo directivo'],
        ['value' => 'convivencia', 'label' => 'Convivencia'],
        ['value' => 'psicosocial', 'label' => 'Psicosocial'],
    ];

    protected $table = 'infirmary_attention_treatments';

    protected $fillable = [
        'attention_id',
        'treatment_types',
        'treatment_categories',
        'derivation_type',
        'derivation_support_teams',
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
        'treatment_categories' => 'array',
        'derivation_support_teams' => 'array',
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
