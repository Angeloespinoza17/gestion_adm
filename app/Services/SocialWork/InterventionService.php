<?php
namespace App\Services\SocialWork;
use App\Events\SocialWork\SocialInterventionCreated;
use App\Models\SocialWork\Intervention;
use App\Models\SocialWork\SocialCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class InterventionService {
    public function __construct(private readonly AuditService $audit) {}
    public function create(?SocialCase $case, array $data, User $user): Intervention {
        if ($case?->status === 'cerrado') throw ValidationException::withMessages(['case_id' => 'Un caso cerrado no acepta acciones ordinarias; debe reabrirse.']);
        return DB::transaction(function () use ($case, $data, $user) {
            $commitments = $data['commitments'] ?? []; unset($data['commitments']);
            $intervention = Intervention::create(array_merge($data, ['case_id' => $case?->id, 'student_profile_id' => $data['student_profile_id'] ?? $case?->primary_student_id, 'responsible_user_id' => $data['responsible_user_id'] ?? $user->id, 'created_by' => $user->id, 'updated_by' => $user->id]));
            foreach ($commitments as $commitment) $intervention->commitments()->create(array_merge($commitment, ['case_id' => $case?->id]));
            if ($case) $case->update(['last_activity_at' => now(), 'next_milestone' => $data['next_action'] ?? $case->next_milestone, 'updated_by' => $user->id]);
            $this->audit->record('intervention.created', $intervention, $user, [], ['kind' => $intervention->kind, 'case_id' => $case?->id]);
            event(new SocialInterventionCreated($intervention));
            return $intervention->load('commitments');
        });
    }
}
