<?php

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TaskStatisticsService
{
    public function __construct(
        private readonly TaskAccessService $accessService,
    ) {
    }

    public function build(Request $request, User $user): array
    {
        $query = $this->accessService->visibleQuery($user);
        return $this->buildFromQuery($request, $query);
    }

    public function buildFromQuery(Request $request, Builder $query): array
    {
        TaskQueryFilters::apply($query, $request);

        $tasks = $query->with('stakeholders:id,name')->get();
        $today = today();
        $nextWeek = today()->addDays(7);
        $activeTasks = $tasks->whereNotIn('status', [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED]);
        $completedTasks = $tasks->where('status', Task::STATUS_COMPLETED);

        $completedWithDates = $completedTasks->filter(fn (Task $task) => $task->completed_at !== null && $task->created_at !== null);
        $averageDays = $completedWithDates->isNotEmpty()
            ? round($completedWithDates->avg(fn (Task $task) => Carbon::parse($task->created_at)->diffInDays(Carbon::parse($task->completed_at))), 1)
            : null;

        $total = $tasks->count();
        $completed = $completedTasks->count();

        return [
            'total' => $total,
            'pending' => $tasks->where('status', Task::STATUS_PENDING)->count(),
            'in_progress' => $tasks->where('status', Task::STATUS_IN_PROGRESS)->count(),
            'completed' => $completed,
            'overdue' => $activeTasks->filter(fn (Task $task) => $task->due_date && $task->due_date->isBefore($today))->count(),
            'due_next_7_days' => $activeTasks->filter(fn (Task $task) => $task->due_date && $task->due_date->betweenIncluded($today, $nextWeek))->count(),
            'global_progress' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            'by_priority' => $this->distribution($tasks, 'priority', Task::PRIORITY_OPTIONS),
            'by_status' => $this->distribution($tasks, 'status', Task::STATUS_OPTIONS),
            'by_stakeholder' => $tasks
                ->flatMap(function (Task $task) {
                    $labels = $task->stakeholders->pluck('name');
                    if ($labels->isEmpty() && $task->stakeholder) {
                        $labels->push($task->stakeholder);
                    }

                    return $labels->isEmpty() ? ['Sin stakeholders'] : $labels;
                })
                ->countBy()
                ->map(fn ($count, $label) => ['label' => $label, 'count' => $count])
                ->sortByDesc('count')
                ->values()
                ->all(),
            'created_by_third_parties' => $tasks
                ->filter(fn (Task $task) => (int) $task->created_by_user_id !== (int) $task->owner_user_id)
                ->count(),
            'average_days_to_complete' => $averageDays,
        ];
    }

    private function distribution($tasks, string $field, array $options): array
    {
        return collect($options)
            ->map(function (array $option) use ($tasks, $field) {
                return [
                    'value' => $option['value'],
                    'label' => $option['label'],
                    'count' => $tasks->where($field, $option['value'])->count(),
                ];
            })
            ->values()
            ->all();
    }
}
