<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaCatalogItem;
use App\Models\Convivencia\ConvivenciaPlanAction;
use App\Models\Convivencia\ConvivenciaPlanActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConvivenciaPlanProgressService
{
    public function __construct(private readonly ConvivenciaPlanService $planService) {}

    public function create(ConvivenciaPlanAction $action, array $payload, User $user): ConvivenciaPlanActivity
    {
        return DB::transaction(function () use ($action, $payload, $user): ConvivenciaPlanActivity {
            $action = ConvivenciaPlanAction::query()->lockForUpdate()->findOrFail($action->id);
            $attributes = $this->normalize($payload);
            $this->assertContributionAvailable($action, $attributes['contribution_percent']);
            $activity = $action->activities()->create([
                ...$attributes,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
            $this->recalculate($action, $user);

            return $activity->refresh();
        });
    }

    public function update(ConvivenciaPlanActivity $activity, array $payload, User $user): ConvivenciaPlanActivity
    {
        return DB::transaction(function () use ($activity, $payload, $user): ConvivenciaPlanActivity {
            $action = ConvivenciaPlanAction::query()->lockForUpdate()->findOrFail($activity->plan_action_id);
            $activity = ConvivenciaPlanActivity::query()->lockForUpdate()->findOrFail($activity->id);
            $this->assertCurrentRevision($activity, (int) ($payload['revision'] ?? 0));
            $attributes = $this->normalize($payload);
            unset($attributes['revision']);
            $this->assertContributionAvailable($action, $attributes['contribution_percent'], $activity->id);
            $activity->fill([...$attributes, 'revision' => $activity->revision + 1, 'updated_by' => $user->id])->save();
            $this->recalculate($action, $user);

            return $activity->refresh();
        });
    }

    public function delete(ConvivenciaPlanActivity $activity, User $user, int $revision): void
    {
        DB::transaction(function () use ($activity, $user): void {
            $action = ConvivenciaPlanAction::query()->lockForUpdate()->findOrFail($activity->plan_action_id);
            $activity = ConvivenciaPlanActivity::query()->lockForUpdate()->findOrFail($activity->id);
            $this->assertCurrentRevision($activity, $revision);
            $activity->delete();
            $this->recalculate($action, $user);
        });
    }

    public function recalculate(ConvivenciaPlanAction $action, User $user): void
    {
        $metrics = $action->activities()
            ->selectRaw('COALESCE(SUM(contribution_percent), 0) as allocated')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'cancelada' THEN 0 ELSE contribution_percent * completion_percent / 100 END), 0) as earned")
            ->selectRaw("SUM(CASE WHEN status IN ('en_ejecucion', 'realizada') THEN 1 ELSE 0 END) as started")
            ->first();

        $progress = min(100, max(0, (int) round((float) ($metrics->earned ?? 0))));
        $status = $this->actionStatus($action, $progress, (int) ($metrics->started ?? 0));
        $action->forceFill([
            'advance_percentage' => $progress,
            'status' => $status,
        ])->save();

        $plan = $action->plan;
        $this->planService->recalculatePlanProgress($plan);
        $plan->forceFill(['updated_by' => $user->id])->save();
    }

    private function normalize(array $payload): array
    {
        $activityType = ConvivenciaCatalogItem::query()
            ->whereKey($payload['activity_type_item_id'] ?? null)
            ->where('group', ConvivenciaPlanActivity::TYPE_GROUP)
            ->firstOrFail();
        $status = (string) ($payload['status'] ?? 'programada');
        $completion = (int) ($payload['completion_percent'] ?? 0);
        $payload['completion_percent'] = match ($status) {
            'realizada' => 100,
            'programada', 'cancelada' => 0,
            default => min(99, max(0, $completion)),
        };
        $payload['contribution_percent'] = min(100, max(0, (int) ($payload['contribution_percent'] ?? 0)));
        $payload['activity_type_item_id'] = $activityType->id;
        $payload['activity_type_label'] = $activityType->name;

        return $payload;
    }

    private function assertContributionAvailable(ConvivenciaPlanAction $action, int $contribution, ?int $exceptId = null): void
    {
        $allocated = (int) $action->activities()
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->sum('contribution_percent');

        if ($allocated + $contribution > 100) {
            throw ValidationException::withMessages([
                'contribution_percent' => ['El aporte total de las actividades no puede superar el 100%.'],
            ]);
        }
    }

    private function actionStatus(ConvivenciaPlanAction $action, int $progress, int $started): string
    {
        if (in_array($action->status, ['postergada', 'cancelada'], true)) {
            return $action->status;
        }
        if ($progress >= 100) {
            return 'completada';
        }

        return $progress > 0 || $started > 0 ? 'en_ejecucion' : 'planificada';
    }

    private function assertCurrentRevision(ConvivenciaPlanActivity $activity, int $revision): void
    {
        if ($revision !== (int) $activity->revision) {
            throw ValidationException::withMessages([
                'revision' => ['La actividad fue actualizada por otra persona. Recarga su versión vigente antes de guardar.'],
            ]);
        }
    }
}
