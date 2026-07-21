<?php

namespace App\Models\Infirmary;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class InfirmaryMedicationAuthorization extends Model
{
    use HasFactory;

    public const STATUS_VIGENTE = 'vigente';

    public const STATUS_PROXIMA_A_VENCER = 'proxima_a_vencer';

    public const STATUS_VENCIDA = 'vencida';

    public const STATUS_TERMINADA = 'terminada';

    public const REGIMEN_PERMANENTE = 'permanente';

    public const REGIMEN_MESES = 'meses';

    public const REGIMEN_SEMANAS = 'semanas';

    public const REGIMEN_DIAS = 'dias';

    public const REGIMEN_FECHA_ESPECIFICA = 'fecha_especifica';

    public const REGIMEN_SOS = 'sos';

    public const SCHEDULE_FIXED_TIME = 'fixed_time';

    public const SCHEDULE_FLEXIBLE = 'flexible';

    public const REGIMEN_OPTIONS = [
        ['value' => self::REGIMEN_PERMANENTE, 'label' => 'Uso permanente'],
        ['value' => self::REGIMEN_MESES, 'label' => 'Por meses'],
        ['value' => self::REGIMEN_SEMANAS, 'label' => 'Por semanas'],
        ['value' => self::REGIMEN_DIAS, 'label' => 'Por días'],
        ['value' => self::REGIMEN_FECHA_ESPECIFICA, 'label' => 'Fecha específica'],
        ['value' => self::REGIMEN_SOS, 'label' => 'S.O.S.'],
    ];

    public const DOSE_UNIT_OPTIONS = [
        ['value' => 'mg', 'label' => 'Miligramos'],
        ['value' => 'cc', 'label' => 'cc'],
    ];

    public const ADMINISTRATION_ROUTE_OPTIONS = [
        ['value' => 'oral', 'label' => 'Vía oral'],
        ['value' => 'topica', 'label' => 'Vía tópica'],
    ];

    public const SCHEDULE_MODE_OPTIONS = [
        ['value' => self::SCHEDULE_FIXED_TIME, 'label' => 'Con horario definido'],
        ['value' => self::SCHEDULE_FLEXIBLE, 'label' => 'Sin horario fijo'],
    ];

    protected $table = 'infirmary_medication_authorizations';

    protected $fillable = [
        'student_profile_id',
        'medication_id',
        'diagnosis',
        'dose',
        'dose_amount',
        'dose_unit',
        'administration_route',
        'frequency',
        'daily_dose_count',
        'schedule_mode',
        'schedule_text',
        'regimen_type',
        'duration_quantity',
        'start_date',
        'end_date',
        'physician_name',
        'medical_authorization_expires_at',
        'guardian_authorization_expires_at',
        'observations',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'dose_amount' => 'decimal:2',
        'daily_dose_count' => 'integer',
        'duration_quantity' => 'integer',
        'medical_authorization_expires_at' => 'date:Y-m-d',
        'guardian_authorization_expires_at' => 'date:Y-m-d',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(InfirmaryMedication::class, 'medication_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function administrations(): HasMany
    {
        return $this->hasMany(InfirmaryMedicationAdministration::class, 'authorization_id')->latest('administered_at')->latest('id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(InfirmaryMedicationSchedule::class, 'authorization_id')->orderBy('dose_order');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(InfirmaryDocument::class, 'documentable')->latest('id');
    }
}
