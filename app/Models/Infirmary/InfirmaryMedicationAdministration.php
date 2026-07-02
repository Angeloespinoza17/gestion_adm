<?php

namespace App\Models\Infirmary;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfirmaryMedicationAdministration extends Model
{
    use HasFactory;

    protected $table = 'infirmary_medication_administrations';

    protected $fillable = [
        'authorization_id',
        'attention_id',
        'medication_id',
        'student_profile_id',
        'administered_at',
        'administered_by_user_id',
        'quantity_administered',
        'schedule_reference',
        'source_type',
        'observations',
    ];

    protected $casts = [
        'administered_at' => 'datetime:Y-m-d H:i:s',
        'quantity_administered' => 'decimal:2',
    ];

    public function authorization(): BelongsTo
    {
        return $this->belongsTo(InfirmaryMedicationAuthorization::class, 'authorization_id');
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

    public function administeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by_user_id');
    }
}
