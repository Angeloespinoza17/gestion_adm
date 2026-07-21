<?php

namespace App\Models\Infirmary;

use App\Models\Staff;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfirmaryMedicationAdministration extends Model
{
    use HasFactory;

    public const STATUS_ADMINISTRADA = 'administrada';

    public const STATUS_NO_ADMINISTRADA = 'no_administrada';

    public const STATUS_OPTIONS = [
        ['value' => self::STATUS_ADMINISTRADA, 'label' => 'Administrada'],
        ['value' => self::STATUS_NO_ADMINISTRADA, 'label' => 'No administrada'],
    ];

    public const NON_ADMINISTRATION_REASON_OPTIONS = [
        ['value' => 'estudiante_ausente', 'label' => 'Estudiante ausente'],
        ['value' => 'rechazo_estudiante', 'label' => 'Rechazo de la estudiante'],
        ['value' => 'sin_autorizacion', 'label' => 'Sin autorización vigente'],
        ['value' => 'sin_stock', 'label' => 'Sin stock disponible'],
        ['value' => 'suspension_medica', 'label' => 'Suspensión médica'],
        ['value' => 'otro', 'label' => 'Otro'],
    ];

    protected $table = 'infirmary_medication_administrations';

    protected $fillable = [
        'authorization_id',
        'schedule_id',
        'attention_id',
        'medication_id',
        'student_profile_id',
        'staff_id',
        'administered_at',
        'scheduled_for_date',
        'administration_status',
        'administered_by_user_id',
        'quantity_administered',
        'dose_amount',
        'dose_unit',
        'administration_route',
        'schedule_reference',
        'non_administration_reason',
        'source_type',
        'observations',
    ];

    protected $casts = [
        'administered_at' => 'datetime:Y-m-d H:i:s',
        'scheduled_for_date' => 'date:Y-m-d',
        'quantity_administered' => 'decimal:2',
        'dose_amount' => 'decimal:2',
    ];

    public function authorization(): BelongsTo
    {
        return $this->belongsTo(InfirmaryMedicationAuthorization::class, 'authorization_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(InfirmaryMedicationSchedule::class, 'schedule_id');
    }

    public function attention(): BelongsTo
    {
        return $this->belongsTo(InfirmaryAttention::class, 'attention_id');
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(InfirmaryMedication::class, 'medication_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function administeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by_user_id');
    }
}
