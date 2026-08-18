<?php

namespace App\Services\SocialWork;

use App\Events\SocialWork\SocialInterventionCreated;
use App\Models\SocialWork\Intervention;
use App\Models\SocialWork\SocialCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InterventionService
{
    public function __construct(private readonly AuditService $audit) {}

    public function create(?SocialCase $case, array $data, User $user): Intervention
    {
        if ($case?->status === 'cerrado') {
            throw ValidationException::withMessages(['case_id' => 'Un caso cerrado no acepta acciones ordinarias; debe reabrirse.']);
        }

        return DB::transaction(function () use ($case, $data, $user) {
            $commitments = $data['commitments'] ?? [];
            unset($data['commitments']);
            $data = $this->prepareParticipants($case, $data);
            $intervention = Intervention::create(array_merge($data, ['case_id' => $case?->id, 'student_profile_id' => $data['student_profile_id'] ?? $case?->primary_student_id, 'responsible_user_id' => $data['responsible_user_id'] ?? $user->id, 'created_by' => $user->id, 'updated_by' => $user->id]));
            foreach ($commitments as $commitment) {
                $intervention->commitments()->create(array_merge($commitment, ['case_id' => $case?->id]));
            }
            if ($case) {
                $case->update(['last_activity_at' => now(), 'next_milestone' => $data['next_action'] ?? $case->next_milestone, 'updated_by' => $user->id]);
            }
            $this->audit->record('intervention.created', $intervention, $user, [], ['kind' => $intervention->kind, 'case_id' => $case?->id]);
            event(new SocialInterventionCreated($intervention));

            return $intervention->load('commitments');
        });
    }

    private function prepareParticipants(?SocialCase $case, array $data): array
    {
        $participantTypes = array_values(array_unique($data['participant_types'] ?? []));
        $participantStaffIds = array_values(array_unique(array_map('intval', $data['participant_staff_ids'] ?? [])));
        $supportStaffIds = array_values(array_unique(array_map('intval', $data['support_staff_ids'] ?? [])));

        unset($data['participant_types'], $data['participant_staff_ids'], $data['support_staff_ids']);

        if ($participantTypes === [] && $participantStaffIds === [] && $supportStaffIds === []) {
            return $data;
        }

        $staff = User::query()
            ->whereIn('id', array_values(array_unique([...$participantStaffIds, ...$supportStaffIds])))
            ->with(['cargo:id,name', 'staff.cargo:id,name'])
            ->get()
            ->keyBy('id');
        $student = $case?->student()->first();
        $participants = [];

        if (in_array('student', $participantTypes, true)) {
            $participants[] = [
                'type' => 'student',
                'role' => 'participant',
                'student_profile_id' => $student?->id,
                'name' => $student?->registered_name_resolved ?: 'Estudiante del caso',
            ];
        }

        if (in_array('guardian', $participantTypes, true)) {
            $participants[] = [
                'type' => 'guardian',
                'role' => 'participant',
                'name' => $student?->guardian_name ?: 'Apoderado/a de la estudiante',
                'relationship' => 'apoderado',
            ];
        }

        foreach ($participantStaffIds as $staffId) {
            if ($person = $staff->get($staffId)) {
                $participants[] = $this->staffSnapshot($person, 'participant');
            }
        }

        foreach ($supportStaffIds as $staffId) {
            if ($person = $staff->get($staffId)) {
                $participants[] = $this->staffSnapshot($person, 'support');
            }
        }

        $data['participants'] = $participants;
        $data['structured_data'] = array_merge($data['structured_data'] ?? [], [
            'participant_types' => $participantTypes,
            'participant_staff_ids' => $participantStaffIds,
            'support_staff_ids' => $supportStaffIds,
        ]);

        return $data;
    }

    private function staffSnapshot(User $user, string $role): array
    {
        return [
            'type' => 'staff',
            'role' => $role,
            'user_id' => $user->id,
            'name' => $user->staff?->full_name ?: $user->name,
            'position' => $user->cargo?->name ?: $user->staff?->cargo?->name,
        ];
    }
}
