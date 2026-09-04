<?php

namespace App\Services\Orientation;

use App\Models\Orientation\OrientationAction;
use App\Models\Orientation\OrientationActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrientationActivityProgressService
{
    public function create(OrientationAction $action, array $data, ?int $userId): OrientationActivity
    {
        return DB::transaction(function () use ($action, $data, $userId): OrientationActivity {
            $lockedAction = OrientationAction::query()->lockForUpdate()->findOrFail($action->id);
            $payload = $this->normalizedPayload($data);

            $this->assertContributionAvailable($lockedAction, (int) $payload['contribution_percent']);

            $activity = $lockedAction->activities()->create([
                ...$payload,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->recalculateAction($lockedAction, $userId);

            return $activity;
        });
    }

    public function update(OrientationActivity $activity, array $data, ?int $userId): OrientationActivity
    {
        return DB::transaction(function () use ($activity, $data, $userId): OrientationActivity {
            $lockedAction = OrientationAction::query()->lockForUpdate()->findOrFail($activity->orientation_action_id);
            $lockedActivity = OrientationActivity::query()->lockForUpdate()->findOrFail($activity->id);
            $payload = $this->normalizedPayload($data);
            $hadAutomaticProgress = $lockedAction->activities()
                ->where('contribution_percent', '>', 0)
                ->exists();

            $this->assertContributionAvailable(
                $lockedAction,
                (int) $payload['contribution_percent'],
                $lockedActivity->id,
            );

            $lockedActivity->update([
                ...$payload,
                'updated_by' => $userId,
            ]);

            $this->recalculateAction($lockedAction, $userId, $hadAutomaticProgress);

            return $lockedActivity->refresh();
        });
    }

    public function recalculateAction(
        OrientationAction $action,
        ?int $userId = null,
        bool $resetWhenUnallocated = false,
    ): OrientationAction
    {
        $metrics = $action->activities()
            ->selectRaw('COALESCE(SUM(contribution_percent), 0) as allocated')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 0 ELSE contribution_percent * completion_percent / 100 END), 0) as earned")
            ->selectRaw("SUM(CASE WHEN contribution_percent > 0 AND status IN ('in_progress', 'completed') THEN 1 ELSE 0 END) as started")
            ->first();

        if ((int) ($metrics->allocated ?? 0) === 0 && ! $resetWhenUnallocated) {
            return $action->refresh();
        }

        $progress = min(100, max(0, (int) round((float) ($metrics->earned ?? 0))));
        $status = $this->actionStatus($action, $progress, (int) ($metrics->started ?? 0));

        $action->forceFill([
            'progress' => $progress,
            'status' => $status,
            'updated_by' => $userId ?: $action->updated_by,
        ])->save();

        return $action->refresh();
    }

    private function normalizedPayload(array $data): array
    {
        $status = (string) ($data['status'] ?? 'scheduled');
        $completion = (int) ($data['completion_percent'] ?? 0);

        $data['completion_percent'] = match ($status) {
            'completed' => 100,
            'scheduled', 'cancelled' => 0,
            default => min(99, max(0, $completion)),
        };
        $data['contribution_percent'] = min(100, max(0, (int) ($data['contribution_percent'] ?? 0)));

        return $data;
    }

    private function assertContributionAvailable(
        OrientationAction $action,
        int $contribution,
        ?int $exceptActivityId = null,
    ): void {
        $allocated = (int) $action->activities()
            ->when($exceptActivityId, fn ($query) => $query->where('id', '<>', $exceptActivityId))
            ->sum('contribution_percent');

        if ($allocated + $contribution <= 100) {
            return;
        }

        $available = max(0, 100 - $allocated);
        throw ValidationException::withMessages([
            'contribution_percent' => "El aporte total de las actividades no puede superar 100%. Queda {$available}% disponible en esta acción.",
        ]);
    }

    private function actionStatus(OrientationAction $action, int $progress, int $startedActivities): string
    {
        if (in_array($action->status, ['postponed', 'cancelled'], true)) {
            return $action->status;
        }

        if ($progress >= 100) {
            return 'completed';
        }

        if ($progress > 0 || $startedActivities > 0) {
            return 'in_progress';
        }

        return 'planned';
    }
}
