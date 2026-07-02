<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConvivenciaPlanService
{
    public function __construct(
        private readonly ConvivenciaSupportService $supportService,
    ) {
    }

    public function store(array $payload, User $user): ConvivenciaPlan
    {
        return DB::transaction(function () use ($payload, $user) {
            $plan = new ConvivenciaPlan();
            $this->fillPlan($plan, $payload, $user, true);
            $plan->save();

            $this->supportService->syncPlanActions($plan, $payload['actions'] ?? []);
            $this->supportService->logStatus($plan, null, $plan->status, $user, 'Plan creado.', 'created');

            return $this->loadPlan($plan);
        });
    }

    public function update(ConvivenciaPlan $plan, array $payload, User $user): ConvivenciaPlan
    {
        return DB::transaction(function () use ($plan, $payload, $user) {
            $previousStatus = $plan->status;

            $this->fillPlan($plan, $payload, $user, false);
            $plan->save();

            $this->supportService->syncPlanActions($plan, $payload['actions'] ?? []);

            if ($previousStatus !== $plan->status) {
                $this->supportService->logStatus($plan, $previousStatus, $plan->status, $user);
            }

            return $this->loadPlan($plan);
        });
    }

    private function fillPlan(ConvivenciaPlan $plan, array $payload, User $user, bool $creating): void
    {
        $plan->fill([
            'academic_year_id' => $payload['academic_year_id'] ?? null,
            'responsible_user_id' => $payload['responsible_user_id'] ?? $user->id,
            'responsible_staff_id' => $payload['responsible_staff_id'] ?? $user->staff_id,
            'name' => $payload['name'],
            'general_objective' => $payload['general_objective'],
            'specific_objectives' => array_values($payload['specific_objectives'] ?? []),
            'resources_required' => $payload['resources_required'] ?? null,
            'indicators_summary' => $payload['indicators_summary'] ?? null,
            'verification_means_summary' => $payload['verification_means_summary'] ?? null,
            'status' => $payload['status'],
            'advance_percentage' => $payload['advance_percentage'] ?? 0,
            'starts_on' => $payload['starts_on'] ?? null,
            'ends_on' => $payload['ends_on'] ?? null,
            'observations' => $payload['observations'] ?? null,
            'final_evaluation' => $payload['final_evaluation'] ?? null,
            'is_sensitive' => (bool) ($payload['is_sensitive'] ?? false),
            'updated_by' => $user->id,
        ]);

        if ($creating) {
            $plan->created_by = $user->id;
        }
    }

    private function loadPlan(ConvivenciaPlan $plan): ConvivenciaPlan
    {
        return $plan->fresh([
            'academicYear:id,name,year',
            'responsibleUser:id,name',
            'responsibleStaff:id,full_name',
            'actions.dimension:id,name',
            'actions.responsibleUser:id,name',
            'actions.responsibleStaff:id,full_name',
            'actions.responsibleDepartment:id,name',
            'attachments.uploadedBy:id,name',
        ]);
    }
}
