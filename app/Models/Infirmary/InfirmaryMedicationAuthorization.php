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

    protected $table = 'infirmary_medication_authorizations';

    protected $fillable = [
        'student_profile_id',
        'medication_id',
        'diagnosis',
        'dose',
        'frequency',
        'schedule_text',
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

    public function documents(): MorphMany
    {
        return $this->morphMany(InfirmaryDocument::class, 'documentable')->latest('id');
    }
}
