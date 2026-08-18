<?php

namespace App\Models\Psychology;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PsychologyActivity extends Model
{
    use SoftDeletes;

    protected $table = 'psychology_activities';

    protected $fillable = ['case_id', 'responsible_user_id', 'type', 'interview_number', 'activity_on', 'starts_at', 'ends_at', 'modality', 'location', 'participants', 'participant_types', 'interviewee_type', 'interviewee_name', 'interviewee_rut', 'interviewer_name_snapshot', 'interviewer_position_snapshot', 'objective', 'institutional_summary', 'private_note', 'general_background', 'result', 'agreements', 'next_steps', 'next_action_on', 'next_interview_at', 'acknowledgement_status', 'acknowledged_name', 'acknowledged_rut', 'acknowledged_at', 'acknowledgement_observations', 'attendance_status', 'visibility', 'referral_feedback', 'status', 'finalized_at', 'finalized_by', 'created_by', 'updated_by'];

    protected $casts = ['activity_on' => 'date:Y-m-d', 'participant_types' => 'array', 'next_action_on' => 'date:Y-m-d', 'next_interview_at' => 'datetime', 'acknowledged_at' => 'datetime', 'finalized_at' => 'datetime', 'private_note' => 'encrypted', 'general_background' => 'encrypted', 'interviewee_rut' => 'encrypted', 'acknowledged_rut' => 'encrypted'];

    public function case(): BelongsTo
    {
        return $this->belongsTo(PsychologyCase::class, 'case_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function addenda(): HasMany
    {
        return $this->hasMany(PsychologyActivityAddendum::class, 'activity_id')->latest();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PsychologyDocument::class, 'activity_id');
    }
}
