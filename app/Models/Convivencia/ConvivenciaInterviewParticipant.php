<?php

namespace App\Models\Convivencia;

use App\Models\Staff;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConvivenciaInterviewParticipant extends Model
{
    use HasFactory;

    protected $table = 'convivencia_interview_participants';

    protected $fillable = [
        'interview_id',
        'student_profile_id',
        'user_id',
        'staff_id',
        'participant_type',
        'participant_role',
        'full_name',
        'contact_reference',
        'notes',
    ];

    public function interview(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaInterview::class, 'interview_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
