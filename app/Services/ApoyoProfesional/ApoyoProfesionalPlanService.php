<?php

namespace App\Services\ApoyoProfesional;

use App\Models\ApoyoProfesional\ApoyoPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApoyoProfesionalPlanService
{
    public function store(array $payload, User $user): ApoyoPlan
    {
        return DB::transaction(function () use ($payload, $user) {
            $plan = new ApoyoPlan();
            $this->fillPlan($plan, $payload, $user, true);
            $plan->save();

            $this->syncActions($plan, $payload['actions'] ?? []);

            return $this->loadPlan($plan);
        });
    }

    public function update(ApoyoPlan $plan, array $payload, User $user): ApoyoPlan
    {
        return DB::transaction(function () use ($plan, $payload, $user) {
            $this->fillPlan($plan, $payload, $user, false);
            $plan->save();

            $this->syncActions($plan, $payload['actions'] ?? []);

            return $this->loadPlan($plan);
        });
    }

    private function fillPlan(ApoyoPlan $plan, array $payload, User $user, bool $creating): void
    {
        $accessProfile = app(ApoyoProfesionalAccessService::class)->professionalProfileForUser($user);
        $area = app(ApoyoProfesionalAccessService::class)->professionalAreaForUser($user);

        $plan->fill([
            'student_profile_id' => $payload['student_profile_id'],
            'responsible_professional_id' => $payload['responsible_professional_id'] ?? $accessProfile?->id,
            'responsible_user_id' => $payload['responsible_user_id'] ?? $user->id,
            'area_slug' => $payload['area_slug'] ?? $area['slug'],
            'area_name' => $payload['area_name'] ?? $area['name'],
            'motive' => $payload['motive'],
            'general_objective' => $payload['general_objective'],
            'specific_objectives' => array_values($payload['specific_objectives'] ?? []),
            'actions_summary' => $payload['actions_summary'] ?? null,
            'responsibles_summary' => $payload['responsibles_summary'] ?? null,
            'start_date' => $payload['start_date'],
            'end_date' => $payload['end_date'] ?? null,
            'indicators' => $payload['indicators'] ?? null,
            'status' => $payload['status'],
            'evidences' => $payload['evidences'] ?? null,
            'observations' => $payload['observations'] ?? null,
            'confidentiality_level' => $payload['confidentiality_level'] ?? 'reservada',
            'updated_by' => $user->id,
        ]);

        if ($creating) {
            $plan->created_by = $user->id;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     */
    private function syncActions(ApoyoPlan $plan, array $actions): void
    {
        $plan->actions()->delete();

        foreach ($actions as $action) {
            if (empty($action['action_description'])) {
                continue;
            }

            $plan->actions()->create([
                'action_description' => $action['action_description'],
                'responsible_label' => $action['responsible_label'] ?? null,
                'due_date' => $action['due_date'] ?? null,
                'completed_at' => !empty($action['completed_at']) ? $action['completed_at'] : null,
                'status' => $action['status'] ?? 'pendiente',
                'observations' => $action['observations'] ?? null,
            ]);
        }
    }

    private function loadPlan(ApoyoPlan $plan): ApoyoPlan
    {
        return $plan->fresh([
            'student:id,first_name,last_name,registered_name,rut',
            'responsibleProfessional.staff:id,full_name',
            'responsibleUser:id,name',
            'actions',
            'documents.uploadedBy:id,name',
        ]);
    }
}
